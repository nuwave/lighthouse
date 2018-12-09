<?php

namespace Nuwave\Lighthouse\Schema\Factories;

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
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\HasErrorBuffer;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath;
use Nuwave\Lighthouse\Support\Traits\HasResolverArguments;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;

class FieldFactory
{
    use HasResolverArguments;

    /**
     * @var ErrorBuffer
     */
    protected $validationErrorBuffer;

    /**
     * @var FieldValue
     */
    protected $fieldValue;

    /**
     * @var QueryFilter
     */
    protected $queryFilter;

    /**
     * @var ArgumentValue
     */
    protected $currentArgumentValueInstance;

    /** @var DirectiveRegistry */
    protected $directiveRegistry;
    /** @var ArgumentFactory */
    protected $argumentFactory;
    /** @var Pipeline */
    protected $pipeline;

    /**
     * @param DirectiveRegistry $directiveRegistry
     * @param ArgumentFactory   $argumentFactory
     * @param Pipeline          $pipeline
     */
    public function __construct(DirectiveRegistry $directiveRegistry, ArgumentFactory $argumentFactory, Pipeline $pipeline)
    {
        $this->directiveRegistry = $directiveRegistry;
        $this->argumentFactory = $argumentFactory;
        $this->pipeline = $pipeline;
    }

    /**
     * Convert a FieldValue to an executable FieldDefinition.
     *
     * @param FieldValue $fieldValue
     *
     * @throws DirectiveException
     *
     * @return array Configuration array for a FieldDefinition
     */
    public function handle(FieldValue $fieldValue): array
    {
        $this->fieldValue = $fieldValue;
        $fieldDefinitionNode = $fieldValue->getField();

        // Get the initial resolver from the FieldValue
        // This is either the webonyx default resolver or provided by a directive
        if ($fieldResolver = $this->directiveRegistry->fieldResolver($fieldDefinitionNode)) {
            $this->fieldValue = $fieldResolver->resolveField($fieldValue);
        }
        $resolver = $this->fieldValue->getResolver();

        $argumentValues = $this->getArgumentValues();
        // No need to do handle the arguments if there are no
        // arguments defined for the field
        if ($argumentValues->isNotEmpty()) {
            $this->queryFilter = QueryFilter::getInstance($this->fieldValue);

            $resolver = $this->decorateResolverWithArgs($resolver, $argumentValues);
        }

        $this->fieldValue->setResolver($resolver);
        $resolverWithMiddleware = $this->pipeline
            ->send($this->fieldValue)
            ->through(
                $this->directiveRegistry->fieldMiddleware($fieldDefinitionNode)
            )
            ->via('handleField')
            ->then(
                function (FieldValue $fieldValue): FieldValue {
                    return $fieldValue;
                }
            )
            ->getResolver();

        // To see what is allowed here, look at the validation rules in
        // GraphQL\Type\Definition\FieldDefinition::getDefinition()
        $fieldDefinition = [
            'name' => $fieldDefinitionNode->name->value,
            'type' => $this->fieldValue->getReturnType(),
            'args' => $this->getInputValueDefinitions($argumentValues),
            'resolve' => $resolverWithMiddleware,
            'description' => data_get($fieldDefinitionNode->description, 'value'),
            'complexity' => $this->fieldValue->getComplexity(),
        ];

        return $fieldDefinition;
    }

    /**
     * Get a collection of the fields argument definitions.
     *
     * @return Collection<ArgumentValue>
     */
    protected function getArgumentValues(): Collection
    {
        return collect($this->fieldValue->getField()->arguments)
            ->map(function (InputValueDefinitionNode $inputValueDefinition) {
                return new ArgumentValue($inputValueDefinition, $this->fieldValue);
            });
    }

    /**
     * Call `ArgMiddleware::handleArgument` at the resolving time.
     *
     * This may be used to transform the arguments, log them or do anything else
     * before they reach the final resolver.
     *
     * @param \Closure                  $resolver
     * @param Collection<ArgumentValue> $argumentValues
     *
     * @return \Closure
     */
    public function decorateResolverWithArgs(\Closure $resolver, Collection $argumentValues): \Closure
    {
        return function ($root, array $args, $context = null, ResolveInfo $resolveInfo) use ($resolver, $argumentValues) {
            $this->validationErrorBuffer = resolve(ErrorBuffer::class)->setErrorType('validation');

            $this->setResolverArguments($root, $args, $context, $resolveInfo);

            $argumentValues->each(
                function (ArgumentValue $argumentValue) use (&$args) {
                    $this->currentArgumentValueInstance = $argumentValue;

                    $noValuePassedForThisArgument = ! array_key_exists($argumentValue->getName(), $args);

                    // because we are passing by reference, we need a variable to contain the null value.
                    if ($noValuePassedForThisArgument) {
                        $argValue = new NoValue();
                    } else {
                        $argValue = &$args[$argumentValue->getName()];
                    }

                    $this->handleArgWithAssociatedDirectivesRecursively(
                        $argumentValue->getType(),
                        $argValue,
                        $argumentValue->getAstNode(),
                        [$argumentValue->getName()]
                    );
                }
            );

            if ($this->validationErrorBuffer->hasErrors()) {
                $this->validationErrorBuffer->flush(
                    $this->getValidationErrorMessage()
                );
            }

            // We (ab)use the ResolveInfo as a way of passing down the query filter
            // to the final resolver
            $resolveInfo->queryFilter = $this->queryFilter;

            return $resolver($root, $args, $context, $resolveInfo);
        };
    }

    /**
     * Handle the ArgMiddleware.
     *
     * @param InputType                     $type
     * @param mixed                         $argValue
     * @param InputValueDefinitionNode|null $astNode
     * @param array                         $argumentPath
     */
    protected function handleArgWithAssociatedDirectivesRecursively(InputType $type, &$argValue, InputValueDefinitionNode $astNode, array $argumentPath)
    {
        if ($argValue instanceof NoValue || null === $argValue) {
            // No rules apply to input objects themselves, so we can stop looking further
            if ($type instanceof InputObjectType) {
                return;
            }

            // There might still be some rules for the list itself
            if ($type instanceof ListOfType) {
                $this->handleArgMiddlewareForArrayDirectives($astNode, $argValue, $argumentPath);

                // No need to consider the rules for the elements of the list, since we know it is empty
                return;
            }

            // all other leaf types
            $this->handleArgWithAssociatedDirectives($astNode, $argValue, $argumentPath);

            return;
        }

        if ($type instanceof InputObjectType) {
            collect($type->getFields())
                ->each(
                    function (InputObjectField $field) use ($argumentPath, &$argValue) {
                        $noValuePassedForThisArgument = ! array_key_exists($field->name, $argValue);

                        // because we are passing by reference, we need a variable to contain the null value.
                        if ($noValuePassedForThisArgument) {
                            $value = new NoValue();
                        } else {
                            $value = &$argValue[$field->name];
                        }

                        $this->handleArgWithAssociatedDirectivesRecursively(
                            $field->type,
                            $value,
                            $field->astNode,
                            $this->addPath($argumentPath, $field->name)
                        );
                    }
                );

            return;
        }

        if ($type instanceof ListOfType) {
            $argValue = $this->handleArgMiddlewareForArrayDirectives($astNode, $argValue, $argumentPath);

            foreach ($argValue as $key => $fieldValue) {
                // here we are passing by reference so the `$argValue[$key]` is intended.
                $this->handleArgWithAssociatedDirectivesRecursively(
                    $type->ofType,
                    $argValue[$key],
                    $astNode,
                    $this->addPath($argumentPath, $key)
                );
            }

            return;
        }

        // all other leaf types
        $argValue = $this->handleArgWithAssociatedDirectives($astNode, $argValue, $argumentPath);
    }

    /**
     * @param InputValueDefinitionNode $astNode
     * @param mixed                    $argValue
     * @param array                    $argumentPath
     *
     * @return mixed
     */
    protected function handleArgMiddlewareForArrayDirectives(
        InputValueDefinitionNode $astNode,
        $argValue,
        array $argumentPath
    ) {
        $directives = $this->directiveRegistry->argMiddlewareForArray($astNode);

        return $this->handleArgMiddlewareDirectivesFor($astNode, $argValue, $argumentPath, $directives);
    }

    /**
     * @param InputValueDefinitionNode $astNode
     * @param mixed                    $argValue
     * @param array                    $argumentPath
     * @param Collection               $directives
     *
     * @return mixed
     */
    protected function handleArgMiddlewareDirectivesFor(
        InputValueDefinitionNode $astNode,
        $argValue,
        array $argumentPath,
        Collection $directives
    ) {
        if ($directives->isEmpty()) {
            return $argValue;
        }

        $this->prepareDirectives($astNode, $argumentPath, $directives);

        return $this->pipeline
            ->send($argValue)
            ->through($directives)
            ->via('handleArgument')
            ->then(function ($value) {
                return $value;
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
            if ($directive instanceof HasErrorBuffer) {
                $directive->setErrorBuffer($this->validationErrorBuffer);
            }

            if ($directive instanceof HasArgumentPath) {
                $directive->setArgumentPath($argumentPath);
            }
        });
    }

    /**
     * @param InputValueDefinitionNode $astNode
     * @param mixed                    $argValue
     * @param array                    $argumentPath
     *
     * @return mixed
     */
    protected function handleArgWithAssociatedDirectives(
        InputValueDefinitionNode $astNode,
        $argValue,
        array $argumentPath
    ) {
        $argValue = $this->handleArgMiddlewareDirectives($astNode, $argValue, $argumentPath);

        $this->handleArgFilterDirectives($astNode, $argumentPath);

        return $argValue;
    }

    /**
     * @param InputValueDefinitionNode $astNode
     * @param mixed                    $argValue
     * @param array                    $argumentPath
     *
     * @return mixed
     */
    protected function handleArgMiddlewareDirectives(
        InputValueDefinitionNode $astNode,
        $argValue,
        array $argumentPath
    ) {
        $directives = $this->directiveRegistry->argMiddleware($astNode);

        return $this->handleArgMiddlewareDirectivesFor($astNode, $argValue, $argumentPath, $directives);
    }

    /**
     * @param InputValueDefinitionNode $astNode
     * @param array                    $argumentPath
     */
    protected function handleArgFilterDirectives(
        InputValueDefinitionNode $astNode,
        array $argumentPath
    ) {
        $directives = $this->directiveRegistry->argFilterDirective($astNode);

        if ($directives->isEmpty()) {
            return;
        }

        $this->prepareDirectives($astNode, $argumentPath, $directives);

        $directives->each(function (ArgFilterDirective $directive) use ($astNode) {
            $this->injectArgumentFilter($directive, $astNode);
        });
    }

    /**
     * @param ArgFilterDirective       $argFilterDirective
     * @param InputValueDefinitionNode $inputValueDefinition
     */
    protected function injectArgumentFilter(ArgFilterDirective $argFilterDirective, InputValueDefinitionNode $inputValueDefinition)
    {
        $argumentName = $inputValueDefinition->name->value;
        $directiveDefinition = ASTHelper::directiveDefinition($inputValueDefinition, $argFilterDirective->name());
        $columnName = ASTHelper::directiveArgValue($directiveDefinition, 'key', $argumentName);

        $this->queryFilter->addArgumentFilter(
            $argumentName,
            $columnName,
            $argFilterDirective
        );
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
     * Gather the error messages from each type of directive.
     *
     * @return string
     */
    protected function getValidationErrorMessage(): string
    {
        $path = implode(
            '.',
            $this->resolveInfo()->path
        );

        return "Validation failed for the field [$path].";
    }

    /**
     * Transform the ArgumentValues into the final InputValueDefinitions.
     *
     * @param Collection<ArgumentValue> $argumentValues
     *
     * @return InputValueDefinitionNode[]
     */
    protected function getInputValueDefinitions(Collection $argumentValues): array
    {
        return $argumentValues
            ->mapWithKeys(function (ArgumentValue $argumentValue) {
                return [
                    $argumentValue->getName() => $this->argumentFactory->handle($argumentValue),
                ];
            })
            ->all();
    }
}
