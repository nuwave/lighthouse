<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\ListOfType;
use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Execution\Builder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Type\Definition\InputObjectType;
use Nuwave\Lighthouse\Execution\ErrorBuffer;
use Nuwave\Lighthouse\Execution\QueryFilter;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\HasErrorBuffer;
use Nuwave\Lighthouse\Schema\Directives\SpreadDirective;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath;
use Nuwave\Lighthouse\Support\Contracts\ProvidesResolver;
use Nuwave\Lighthouse\Support\Traits\HasResolverArguments;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgValidationDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver;

class FieldFactory
{
    use HasResolverArguments;

    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory
     */
    protected $directiveFactory;

    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\ArgumentFactory
     */
    protected $argumentFactory;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\ProvidesResolver
     */
    protected $providesResolver;

    /**
     * @var \Nuwave\Lighthouse\Support\Pipeline
     */
    protected $pipeline;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver
     */
    protected $providesSubscriptionResolver;

    /**
     * @var \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    protected $fieldValue;

    /**
     * @var \Nuwave\Lighthouse\Execution\Builder
     */
    protected $builder;

    /**
     * @var \Nuwave\Lighthouse\Execution\QueryFilter
     * @deprecated
     */
    protected $queryFilter;

    /**
     * @var array
     */
    protected $rules = [];

    /**
     * @var array
     */
    protected $messages = [];

    /**
     * @var \Nuwave\Lighthouse\Execution\ErrorBuffer
     */
    protected $validationErrorBuffer;

    /**
     * A snapshot of the arguments that are passed to "handleArgDirectives".
     *
     * This is used to pause and resume the evaluation of arg directives
     * before and after validation.
     *
     * @var mixed[]
     */
    protected $handleArgDirectivesSnapshots = [];

    /**
     * Arg paths to spread out.
     *
     * @var array[]
     */
    protected $pathsToSpread = [];

    /**
     * @param  \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory  $directiveFactory
     * @param  \Nuwave\Lighthouse\Schema\Factories\ArgumentFactory  $argumentFactory
     * @param  \Nuwave\Lighthouse\Support\Pipeline  $pipeline
     * @param  \Nuwave\Lighthouse\Support\Contracts\ProvidesResolver  $providesResolver
     * @param  \Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver  $providesSubscriptionResolver
     * @return void
     */
    public function __construct(
        DirectiveFactory $directiveFactory,
        ArgumentFactory $argumentFactory,
        Pipeline $pipeline,
        ProvidesResolver $providesResolver,
        ProvidesSubscriptionResolver $providesSubscriptionResolver
    ) {
        $this->directiveFactory = $directiveFactory;
        $this->argumentFactory = $argumentFactory;
        $this->pipeline = $pipeline;
        $this->providesResolver = $providesResolver;
        $this->providesSubscriptionResolver = $providesSubscriptionResolver;
    }

    /**
     * Convert a FieldValue to an executable FieldDefinition.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return array Configuration array for a FieldDefinition
     */
    public function handle(FieldValue $fieldValue): array
    {
        $fieldDefinitionNode = $fieldValue->getField();

        // Directives have the first priority for defining a resolver for a field
        if ($resolverDirective = $this->directiveFactory->createFieldResolver($fieldDefinitionNode)) {
            $this->fieldValue = $resolverDirective->resolveField($fieldValue);
        } else {
            $this->fieldValue = $fieldValue->setResolver(
                $fieldValue->getParentName() === 'Subscription'
                    ? $this->providesSubscriptionResolver->provideSubscriptionResolver($fieldValue)
                    : $this->providesResolver->provideResolver($fieldValue)
            );
        }

        $resolverWithMiddleware = $this->pipeline
            ->send($this->fieldValue)
            ->through(
                $this->directiveFactory->createFieldMiddleware($fieldDefinitionNode)
            )
            ->via('handleField')
            ->then(
                function (FieldValue $fieldValue): FieldValue {
                    return $fieldValue;
                }
            )
            ->getResolver();

        $argumentValues = $this->getArgumentValues();

        $this->fieldValue->setResolver(
            function () use ($argumentValues, $resolverWithMiddleware) {
                $this->setResolverArguments(...func_get_args());

                $this->validationErrorBuffer = (new ErrorBuffer)->setErrorType('validation');
                $this->builder = new Builder;

                $this->queryFilter = QueryFilter::getInstance($this->fieldValue);

                $argumentValues->each(
                    function (ArgumentValue $argumentValue): void {
                        $this->handleArgDirectivesRecursively(
                            $argumentValue->getType(),
                            $argumentValue->getAstNode(),
                            [$argumentValue->getName()]
                        );
                    }
                );

                $this->runArgDirectives();

                // Apply the argument spreadings after we are finished with all
                // the other argument handling
                foreach ($this->pathsToSpread as $argumentPath) {
                    $inputValues = $this->argValue($argumentPath);

                    // If no input is given, there is nothing to spread
                    if (! $inputValues) {
                        continue;
                    }

                    // We remove the value from where it was defined before
                    $this->unsetArgValue($argumentPath);

                    // The last part of the path is the name of the input value,
                    // the exact thing we want to remove
                    array_pop($argumentPath);

                    foreach ($inputValues as $key => $value) {
                        $this->setArgValue(
                            array_merge($argumentPath, [$key]),
                            $value
                        );
                    }
                }

                $this->builder->setQueryFilter(
                    $this->queryFilter
                );

                // The final resolver can access the builder through the ResolveInfo
                $this->resolveInfo->builder = $this->builder;

                return $resolverWithMiddleware($this->root, $this->args, $this->context, $this->resolveInfo);
            }
        );

        // To see what is allowed here, look at the validation rules in
        // GraphQL\Type\Definition\FieldDefinition::getDefinition()
        return [
            'name' => $fieldDefinitionNode->name->value,
            'type' => $this->fieldValue->getReturnType(),
            'args' => $this->getInputValueDefinitions($argumentValues),
            'resolve' => $this->fieldValue->getResolver(),
            'description' => data_get($fieldDefinitionNode->description, 'value'),
            'complexity' => $this->fieldValue->getComplexity(),
            'deprecationReason' => $this->fieldValue->getDeprecationReason(),
        ];
    }

    /**
     * Get a collection of the fields argument definitions.
     *
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Schema\Values\ArgumentValue>
     */
    protected function getArgumentValues(): Collection
    {
        return (new Collection($this->fieldValue->getField()->arguments))
            ->map(function (InputValueDefinitionNode $inputValueDefinition): ArgumentValue {
                return new ArgumentValue($inputValueDefinition, $this->fieldValue);
            });
    }

    /**
     * Handle the ArgMiddleware.
     *
     * @param  \GraphQL\Type\Definition\InputType  $type
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $astNode
     * @param  mixed[]  $argumentPath
     * @return void
     */
    protected function handleArgDirectivesRecursively(
        InputType $type,
        InputValueDefinitionNode $astNode,
        array $argumentPath
    ): void {
        if ($type instanceof NonNull) {
            $this->handleArgDirectivesRecursively(
                $type->getWrappedType(),
                $astNode,
                $argumentPath
            );

            return;
        }

        $directives = $this->directiveFactory->createArgDirectives($astNode);

        if (
            $directives->contains(function (Directive $directive): bool {
                return $directive instanceof SpreadDirective;
            })
            && $type instanceof InputObjectType
        ) {
            $this->pathsToSpread [] = $argumentPath;
        }

        // Handle the argument itself. At this point, it can be wrapped
        // in a list or an input object
        $this->handleArgWithAssociatedDirectives($type, $astNode, $directives, $argumentPath);

        // If we no value or null is given, we bail here to prevent
        // infinitely going down a chain of nested input objects
        if (! $this->argValueExists($argumentPath) || $this->argValue($argumentPath) === null) {
            return;
        }

        if ($type instanceof InputObjectType) {
            foreach ($type->getFields() as $field) {
                $this->handleArgDirectivesRecursively(
                    $field->type,
                    $field->astNode,
                    array_merge($argumentPath, [$field->name])
                );
            }
        }

        if ($type instanceof ListOfType) {
            foreach ($this->argValue($argumentPath) as $index => $value) {
                // here we are passing by reference so the `$argValue[$key]` is intended.
                $this->handleArgDirectivesRecursively(
                    $type->ofType,
                    $astNode,
                    array_merge($argumentPath, [$index])
                );
            }
        }
    }

    /**
     * @param  \GraphQL\Type\Definition\InputType  $type
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $astNode
     * @param  \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\Directive>  $directives
     * @param  mixed[]  $argumentPath
     * @return void
     */
    protected function handleArgWithAssociatedDirectives(
        InputType $type,
        InputValueDefinitionNode $astNode,
        Collection $directives,
        array $argumentPath
    ): void {
        $isArgDirectiveForArray = function (ArgDirective $directive): bool {
            return $directive instanceof ArgDirectiveForArray;
        };

        $this->handleArgDirectives(
            $astNode,
            $argumentPath,
            $type instanceof ListOfType
                ? $directives->filter($isArgDirectiveForArray)
                : $directives->reject($isArgDirectiveForArray)
        );
    }

    /**
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $astNode
     * @param  mixed[]  $argumentPath
     * @param  \Illuminate\Support\Collection  $directives
     * @return void
     */
    protected function handleArgDirectives(
        InputValueDefinitionNode $astNode,
        array $argumentPath,
        Collection $directives
    ): void {
        if ($directives->isEmpty()) {
            return;
        }

        $directives->each(function (Directive $directive) use ($argumentPath): void {
            if ($directive instanceof HasErrorBuffer) {
                $directive->setErrorBuffer($this->validationErrorBuffer);
            }

            if ($directive instanceof HasArgumentPath) {
                $directive->setArgumentPath($argumentPath);
            }
        });

        // Remove the directive from the list to avoid evaluating the same directive twice
        while ($directive = $directives->shift()) {
            // Pause the iteration once we hit any directive that has to do
            // with validation. We will resume running through the remaining
            // directives later, after we completed validation
            if ($directive instanceof ArgValidationDirective) {
                // We gather the rules from all arguments and then run validation in one full swoop
                $this->rules = array_merge($this->rules, $directive->getRules());
                $this->messages = array_merge($this->messages, $directive->getMessages());

                break;
            }

            if ($directive instanceof ArgTransformerDirective && $this->argValueExists($argumentPath)) {
                $this->setArgValue(
                    $argumentPath,
                    $directive->transform($this->argValue($argumentPath))
                );
            }

            if ($directive instanceof ArgBuilderDirective) {
                $this->builder->addBuilderDirective(
                    $astNode->name->value,
                    $directive
                );
            }

            if ($directive instanceof ArgFilterDirective) {
                $argumentName = $astNode->name->value;
                $directiveDefinition = ASTHelper::directiveDefinition($astNode, $directive->name());
                $columnName = ASTHelper::directiveArgValue($directiveDefinition, 'key', $argumentName);

                $this->queryFilter->addArgumentFilter(
                    $argumentName,
                    $columnName,
                    $directive
                );
            }
        }

        // If directives remain, snapshot the state that we are in now
        // to allow resuming after validation has run
        if ($directives->isNotEmpty()) {
            $this->handleArgDirectivesSnapshots[] = [$astNode, $argumentPath, $directives];
        }
    }

    protected function argValueExists(array $argumentPath)
    {
        return Arr::has($this->args, implode('.', $argumentPath));
    }

    protected function setArgValue(array $argumentPath, $value)
    {
        return Arr::set($this->args, implode('.', $argumentPath), $value);
    }

    protected function unsetArgValue(array $argumentPath)
    {
        Arr::forget($this->args, implode('.', $argumentPath));
    }

    protected function argValue(array $argumentPath)
    {
        return Arr::get($this->args, implode('.', $argumentPath));
    }

    protected function runArgDirectives(): void
    {
        $this->validateArgs();
        $this->resumeHandlingArgDirectives();
    }

    /**
     * Run the gathered validation rules on the arguments.
     *
     * @return void
     */
    protected function validateArgs(): void
    {
        if (! $this->rules) {
            return;
        }

        $validator = validator(
            $this->args,
            $this->rules,
            $this->messages,
            [
                'root' => $this->root,
                'context' => $this->context,
                // This makes it so that we get an instance of our own Validator class
                'resolveInfo' => $this->resolveInfo,
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->getMessages() as $key => $errorMessages) {
                foreach ($errorMessages as $errorMessage) {
                    $this->validationErrorBuffer->push($errorMessage, $key);
                }
            }
        }

        $path = implode(
            '.',
            $this->resolveInfo()->path
        );
        $this->validationErrorBuffer->flush(
            "Validation failed for the field [$path]."
        );

        // reset rules and messages
        $this->rules = [];
        $this->messages = [];
    }

    /**
     * Continue evaluating the arg directives after validation has run.
     *
     * @return void
     */
    protected function resumeHandlingArgDirectives(): void
    {
        // copy and reset
        $snapshots = $this->handleArgDirectivesSnapshots;
        $this->handleArgDirectivesSnapshots = [];

        foreach ($snapshots as $handlerArgs) {
            $this->handleArgDirectives(...$handlerArgs);
        }

        // We might have hit more validation-relevant directives so we recurse
        if (count($this->handleArgDirectivesSnapshots) > 0) {
            $this->runArgDirectives();
        }
    }

    /**
     * Transform the ArgumentValues into the final InputValueDefinitions.
     *
     * @param  \Illuminate\Support\Collection<ArgumentValue>  $argumentValues
     * @return \GraphQL\Language\AST\InputValueDefinitionNode[]
     */
    protected function getInputValueDefinitions(Collection $argumentValues): array
    {
        return $argumentValues
            ->mapWithKeys(function (ArgumentValue $argumentValue): array {
                return [
                    $argumentValue->getName() => $this->argumentFactory->handle($argumentValue),
                ];
            })
            ->all();
    }
}
