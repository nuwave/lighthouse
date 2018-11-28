<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Utils\AST;
use Illuminate\Support\Collection;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\ListOfType;
use Nuwave\Lighthouse\Support\Pipeline;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Type\Definition\InputObjectType;
use Nuwave\Lighthouse\Execution\ErrorBuffer;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\HasErrorBuffer;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath;
use Nuwave\Lighthouse\Support\Traits\HasResolverArguments;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;
use Nuwave\Lighthouse\Support\Contracts\HasRootArgumentValue;
use Nuwave\Lighthouse\Support\Traits\CanInjectArgumentFilter;
use Nuwave\Lighthouse\Support\Contracts\HasResolverArguments as HasResolverArgumentsContract;

class ArgumentFactory implements HasResolverArgumentsContract
{
    use HasResolverArguments, CanInjectArgumentFilter;

    /**
     * @var array
     */
    protected $currentInputValueDefinition;

    /**
     * @var ErrorBuffer
     */
    protected $currentErrorBuffer;

    /**
     * @var string[]
     */
    protected $currentDirectiveTypes = [];

    /**
     * @var DirectiveFactory
     */
    protected $directiveFactory;

    /**
     * ArgumentFactory constructor.
     *
     * @param DirectiveFactory $directiveFactory
     */
    public function __construct(DirectiveFactory $directiveFactory)
    {
        $this->directiveFactory = $directiveFactory;
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
     * For example, an argument may be encrypted before reaching the final resolver.
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

            // The list of arguments names that are not provided by the client.
            $originalArguments = $args;

            foreach ($inputValueDefinitions as $argumentName => $inputValueDefinition) {
                $this->currentInputValueDefinition = $inputValueDefinition;
                $this->resetCurrentErrorBuffer();
                $this->resetCurrentDirectiveTypes();

                $this->handleArgWithAssociatedDirectivesRecursively(
                    $inputValueDefinition['type'],
                    $args[$argumentName],
                    $inputValueDefinition['astNode'],
                    [$argumentName]
                );

                if ($this->currentErrorBuffer->hasErrors()) {
                    $this->currentErrorBuffer->flush($this->gatherErrorMessage());
                }
            }

            // Because we are passing the items of `$args` by reference to `$this->handleArgMiddleware`,
            // arguments that were not provided in the original incoming arguments but exist in the
            // `$inputValueDefinitions` can produce a null item to the `$args`. We should remove
            // those newly produced items here before we can pass it to the next resolver.
            $args = array_intersect_key($args, $originalArguments);

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
        if ($type instanceof InputObjectType) {
            foreach ($type->getFields() as $field) {
                if (! array_key_exists($field->name, $argumentValue)) {
                    continue;
                }

                $this->handleArgWithAssociatedDirectivesRecursively(
                    $field->type,
                    $argumentValue[$field->name],
                    $field->astNode,
                    $this->addPath($argumentPath, $field->name)
                );
            }

            return;
        }

        if ($type instanceof ListOfType) {
            foreach ($argumentValue as $key => $fieldValue) {
                // here we are passing by reference so the `$argumentValue[$key]` is intended.
                $this->handleArgWithAssociatedDirectivesRecursively(
                    $type->ofType,
                    $argumentValue[$key],
                    $astNode,
                    $this->addPath($argumentPath, $key)
                );
            }

            return;
        }

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
     * @param $argumentValue
     * @param array $argumentPath
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
     * @param $argumentValue
     * @param array $argumentPath
     *
     * @return mixed
     */
    protected function handleArgMiddlewareDirectives(
        InputValueDefinitionNode $astNode,
        $argumentValue,
        array $argumentPath
    ) {
        $directives = $this->directiveFactory->createArgMiddleware($astNode);

        if ($directives->isEmpty()) {
            return $argumentValue;
        }

        $this->prepareDirective($astNode, $argumentPath, $directives);

        return resolve(Pipeline::class)
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

        $this->prepareDirective($astNode, $argumentPath, $directives);

        $directives->each(function (ArgFilterDirective $directive) use ($astNode) {
            $this->injectArgumentFilter($directive, $astNode);
        });
    }

    /**
     * @param InputValueDefinitionNode $astNode
     * @param array                    $argumentPath
     * @param Collection               $directives
     */
    protected function prepareDirective(InputValueDefinitionNode $astNode, array $argumentPath, Collection $directives)
    {
        $directives->each(function (Directive $directive) use ($astNode, $argumentPath) {
            $this->addCurrentDirectiveType($directive);

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
     * @param ArgFilterDirective       $directive
     * @param InputValueDefinitionNode $astNode
     */
    protected function injectArgumentFilter(ArgFilterDirective $directive, InputValueDefinitionNode $astNode)
    {
        $argFilterType = $directive->type();
        $parentField = $this->currentArgumentValueInstance()->getParentField();
        $argumentName = $astNode->name->value;
        $directiveDefinition = ASTHelper::directiveDefinition($astNode, $directive->name());
        $columnName = ASTHelper::directiveArgValue($directiveDefinition, 'key', $argumentName);

        if (ArgFilterDirective::SINGLE_TYPE === $argFilterType) {
            $this->injectSingleArgumentFilter(
                $argumentName,
                $parentField,
                $directive->filter(),
                $columnName
            );
        }

        if (ArgFilterDirective::MULTI_TYPE === $argFilterType) {
            $this->injectMultiArgumentFilter(
                $argumentName,
                $parentField,
                $directive->name(),
                $directive->filter(),
                $columnName
            );
        }
    }

    /**
     * Add a directive type to current directive types.
     *
     * @param Directive $directive
     */
    protected function addCurrentDirectiveType(Directive $directive)
    {
        $className = \get_class($directive);

        if (\in_array($className, $this->currentDirectiveTypes)) {
            return;
        }

        $this->currentDirectiveTypes[] = $className;
    }

    /**
     * clear the all the current directive types.
     */
    protected function resetCurrentDirectiveTypes()
    {
        $this->currentDirectiveTypes = [];
    }

    /**
     * reset currentErrorBuffer.
     */
    protected function resetCurrentErrorBuffer()
    {
        $this->currentErrorBuffer = resolve(ErrorBuffer::class);
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
    protected function gatherErrorMessage(): string
    {
        $path = implode('.', $this->resolveInfo()->path);

        return collect($this->currentDirectiveTypes)->map(function (string $directiveClass) use ($path) {
            if (method_exists($directiveClass, 'getFlushErrorMessage')) {
                return $directiveClass::getFlushErrorMessage($this->currentArgumentValueInstance(), $path);
            }

            return null;
        })->filter()->implode(PHP_EOL);
    }
}
