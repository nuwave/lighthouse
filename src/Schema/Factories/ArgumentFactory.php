<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Utils\AST;
use Illuminate\Support\Collection;
use GraphQL\Type\Definition\InputType;
use Nuwave\Lighthouse\Support\NoValue;
use GraphQL\Type\Definition\ListOfType;
use Nuwave\Lighthouse\Support\Pipeline;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Type\Definition\InputObjectType;
use Nuwave\Lighthouse\Execution\ErrorBuffer;
use Nuwave\Lighthouse\Execution\QueryFilter;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\HasErrorBuffer;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath;
use Nuwave\Lighthouse\Support\Traits\HasResolverArguments;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;
use Nuwave\Lighthouse\Support\Contracts\HasRootArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\HasResolverArguments as HasResolverArgumentsContract;

class ArgumentFactory implements HasResolverArgumentsContract
{
    use HasResolverArguments;

    /**
     * @var array
     */
    protected $currentInputValueDefinition;

    /**
     * @var ErrorBuffer
     */
    protected $currentErrorBuffer;

    /**
     * @var DirectiveFactory
     */
    protected $directiveFactory;

    /**
     * @var Pipeline
     */
    protected $pipeline;

    /**
     * ArgumentFactory constructor.
     *
     * @param DirectiveFactory $directiveFactory
     * @param Pipeline         $pipeline
     */
    public function __construct(DirectiveFactory $directiveFactory, Pipeline $pipeline)
    {
        $this->directiveFactory = $directiveFactory;
        $this->pipeline = $pipeline;
    }

    /**
     * Convert argument definition to type.
     *
     * @param ArgumentValue $value
     *
     * @throws \Exception
     *
     * @return array
     */
    public function handle(ArgumentValue $value): array
    {
        $definition = $value->getAstNode();

        $fieldArgument = [
            'name' => $definition->name->value,
            'description' => data_get($definition->description, 'value'),
            'type' => $value->getType(),
            'astNode' => $definition,
            '_argumentValueInstance' => $value,
        ];

        if ($defaultValue = $definition->defaultValue) {
            $fieldArgument += [
                'defaultValue' => AST::valueFromASTUntyped($defaultValue),
            ];
        }

        // Add any dynamically declared public properties of the FieldArgument
        $fieldArgument += get_object_vars($value);

        // Used to construct a FieldArgument class
        return $fieldArgument;
    }

    /**
     * Call `ArgMiddleware::handleArgument` at the resolving time.
     *
     * This may be used to transform the arguments, log them or do anything else
     * before they reach the final resolver.
     *
     * @param \Closure          $resolver
     * @param Collection<array> $inputValueDefinitions
     *
     * @return \Closure
     */
    public function handleArgsInResolver(\Closure $resolver, Collection $inputValueDefinitions): \Closure
    {
        return function ($root, $args, $context = null, ResolveInfo $resolveInfo) use ($resolver, $inputValueDefinitions) {
            $this->setResolverArguments($root, $args, $context, $resolveInfo);
            $this->resetCurrentErrorBuffer();

            $inputValueDefinitions->each(function ($inputValueDefinition, $argumentName) use (&$args) {
                $this->currentInputValueDefinition = $inputValueDefinition;

                $noValuePassedForThisArgument = ! array_key_exists($argumentName, $args);

                // because we are passing by reference, we need a variable to contain the null value.
                if ($noValuePassedForThisArgument) {
                    $value = new NoValue();
                } else {
                    $value = &$args[$argumentName];
                }

                $this->handleArgWithAssociatedDirectivesRecursively(
                    $inputValueDefinition['type'],
                    $value,
                    $inputValueDefinition['astNode'],
                    [$argumentName]
                );
            });

            if ($this->currentErrorBuffer->hasErrors()) {
                $this->currentErrorBuffer->flush($this->getErrorMessage());
            }

            return $resolver($root, $args, $context, $resolveInfo);
        };
    }

    /**
     * Handle the ArgMiddleware.
     *
     * @param InputType                     $type
     * @param mixed                         $argumentValue
     * @param InputValueDefinitionNode|null $astNode
     * @param array                         $argumentPath
     */
    protected function handleArgWithAssociatedDirectivesRecursively(InputType $type, &$argumentValue, InputValueDefinitionNode $astNode, array $argumentPath)
    {
        if ($argumentValue instanceof NoValue || null === $argumentValue) {
            if ($type instanceof InputObjectType) {
                return;
            }

            if ($type instanceof ListOfType) {
                $this->handleArgMiddlewareForArrayDirectives($astNode, $argumentValue, $argumentPath);

                return;
            }

            // all other leaf types
            $this->handleArgWithAssociatedDirectives($astNode, $argumentValue, $argumentPath);

            return;
        }

        if ($type instanceof InputObjectType) {
            collect($type->getFields())->each(function (InputObjectField $field) use ($argumentPath, &$argumentValue) {
                $noValuePassedForThisArgument = ! array_key_exists($field->name, $argumentValue);

                // because we are passing by reference, we need a variable to contain the null value.
                if ($noValuePassedForThisArgument) {
                    $value = new NoValue();
                } else {
                    $value = &$argumentValue[$field->name];
                }

                $this->handleArgWithAssociatedDirectivesRecursively(
                    $field->type,
                    $value,
                    $field->astNode,
                    $this->addPath($argumentPath, $field->name)
                );
            });

            return;
        }

        if ($type instanceof ListOfType) {
            $argumentValue = $this->handleArgMiddlewareForArrayDirectives($astNode, $argumentValue, $argumentPath);
            foreach ($argumentValue as $key => $fieldValue) {
                // here we are passing by reference so the `$argumentValue[$key]` is intended.
                $this->handleArgWithAssociatedDirectivesRecursively(
                    $type->ofType,
                    $argumentValue[$key],
                    $astNode,
                    $this->addPath($this->addPath($argumentPath, 'items'), $key)
                );
            }

            return;
        }

        // all other leaf types
        $argumentValue = $this->handleArgWithAssociatedDirectives($astNode, $argumentValue, $argumentPath);
    }

    /**
     * Append a path to the base path to create a new path.
     *
     * @param array      $basePath
     * @param string|int $pathToBeAdded
     *
     * @return array
     */
    protected function addPath(array $basePath, $pathToBeAdded): array
    {
        $basePath[] = $pathToBeAdded;

        return $basePath;
    }

    /**
     * @param InputValueDefinitionNode $astNode
     * @param mixed                    $argumentValue
     * @param array                    $argumentPath
     *
     * @return mixed
     */
    protected function handleArgWithAssociatedDirectives(
        InputValueDefinitionNode $astNode,
        $argumentValue,
        array $argumentPath
    ) {
        $argumentValue = $this->handleArgMiddlewareDirectives($astNode, $argumentValue, $argumentPath);

        $this->handleArgFilterDirectives($astNode, $argumentPath);

        return $argumentValue;
    }

    /**
     * @param InputValueDefinitionNode $astNode
     * @param mixed                    $argumentValue
     * @param array                    $argumentPath
     *
     * @return mixed
     */
    protected function handleArgMiddlewareDirectives(
        InputValueDefinitionNode $astNode,
        $argumentValue,
        array $argumentPath
    ) {
        $directives = $this->directiveFactory->createArgMiddleware($astNode);

        return $this->handleArgMiddlewareDirectivesFor($astNode, $argumentValue, $argumentPath, $directives);
    }

    /**
     * @param InputValueDefinitionNode $astNode
     * @param $argumentValue
     * @param array $argumentPath
     *
     * @return mixed
     */
    protected function handleArgMiddlewareForArrayDirectives(
        InputValueDefinitionNode $astNode,
        $argumentValue,
        array $argumentPath
    ) {
        $directives = $this->directiveFactory->createArgMiddlewareForArray($astNode);

        return $this->handleArgMiddlewareDirectivesFor($astNode, $argumentValue, $argumentPath, $directives);
    }

    /**
     * @param InputValueDefinitionNode $astNode
     * @param $argumentValue
     * @param array      $argumentPath
     * @param Collection $directives
     *
     * @return mixed
     */
    protected function handleArgMiddlewareDirectivesFor(
        InputValueDefinitionNode $astNode,
        $argumentValue,
        array $argumentPath,
        Collection $directives
    ) {
        if ($directives->isEmpty()) {
            return $argumentValue;
        }

        $this->prepareDirectives($astNode, $argumentPath, $directives);

        return $this->handleArgMiddlewareDirectivesThroughPipeline($argumentValue, $directives);
    }

    /**
     * @param $argumentValue
     * @param Collection $directives
     *
     * @return mixed
     */
    protected function handleArgMiddlewareDirectivesThroughPipeline($argumentValue, Collection $directives)
    {
        return $this->pipeline
            ->send($argumentValue)
            ->through($directives)
            ->via('handleArgument')
            ->then(function ($value) {
                return $value;
            });
    }

    /**
     * @param InputValueDefinitionNode $astNode
     * @param array                    $argumentPath
     */
    protected function handleArgFilterDirectives(
        InputValueDefinitionNode $astNode,
        array $argumentPath
    ) {
        $directives = $this->directiveFactory->createArgFilterDirective($astNode);

        if ($directives->isEmpty()) {
            return;
        }

        $this->prepareDirectives($astNode, $argumentPath, $directives);

        $directives->each(function (ArgFilterDirective $directive) use ($astNode) {
            $this->injectArgumentFilter($directive, $astNode);
        });
    }

    /**
     * @param InputValueDefinitionNode $astNode
     * @param array                    $argumentPath
     * @param Collection               $directives
     */
    protected function prepareDirectives(InputValueDefinitionNode $astNode, array $argumentPath, Collection $directives)
    {
        $directives->each(function (Directive $directive) use ($astNode, $argumentPath) {
            if ($directive instanceof HasResolverArgumentsContract) {
                $directive->setResolverArguments(...$this->resolverArguments());
            }

            if ($directive instanceof HasRootArgumentValue) {
                $directive->setRootArgumentValue($this->currentArgumentValueInstance());
            }

            if ($directive instanceof HasErrorBuffer) {
                $directive->setErrorBuffer($this->currentErrorBuffer);
            }

            if ($directive instanceof HasArgumentPath) {
                $directive->setArgumentPath($argumentPath);
            }
        });
    }

    /**
     * @param ArgFilterDirective       $argFilterDirective
     * @param InputValueDefinitionNode $astNode
     */
    protected function injectArgumentFilter(ArgFilterDirective $argFilterDirective, InputValueDefinitionNode $astNode)
    {
        $parentField = $this->currentArgumentValueInstance()
            ->getParentField();

        $argumentName = $astNode->name->value;
        $directiveDefinition = ASTHelper::directiveDefinition($astNode, $argFilterDirective->name());
        $columnName = ASTHelper::directiveArgValue($directiveDefinition, 'key', $argumentName);

        $query = QueryFilter::getInstance($parentField);

        $query->addArgumentFilter(
            $argumentName,
            $columnName,
            $argFilterDirective
        );

        $parentField->injectArg(
            QueryFilter::QUERY_FILTER_KEY,
            $query
        );
    }

    /**
     * reset currentErrorBuffer.
     */
    protected function resetCurrentErrorBuffer()
    {
        $this->currentErrorBuffer = resolve(ErrorBuffer::class)->setErrorType('validation');
    }

    /**
     * @return ArgumentValue
     */
    protected function currentArgumentValueInstance(): ArgumentValue
    {
        return $this->currentInputValueDefinition['_argumentValueInstance'];
    }

    /**
     * Gather the error messages from each type of directive.
     *
     * @return string
     */
    protected function getErrorMessage(): string
    {
        $path = implode('.', $this->resolveInfo()->path);

        return "Validation failed for the field [$path].";
    }
}
