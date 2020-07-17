<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory;
use Nuwave\Lighthouse\Execution\ErrorBuffer;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath;
use Nuwave\Lighthouse\Support\Contracts\HasErrorBuffer;
use Nuwave\Lighthouse\Support\Contracts\ProvidesRules;
use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Support\Traits\HasResolverArguments;

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
     * @var \Nuwave\Lighthouse\Support\Pipeline
     */
    protected $pipeline;

    /**
     * @var \Illuminate\Contracts\Validation\Factory
     */
    protected $validationFactory;

    /**
     * @var \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    protected $fieldValue;

    /**
     * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory
     */
    protected $argumentSetFactory;

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

    public function __construct(
        DirectiveFactory $directiveFactory,
        ArgumentFactory $argumentFactory,
        Pipeline $pipeline,
        ValidationFactory $validationFactory,
        ArgumentSetFactory $argumentSetFactory
    ) {
        $this->directiveFactory = $directiveFactory;
        $this->argumentFactory = $argumentFactory;
        $this->pipeline = $pipeline;
        $this->validationFactory = $validationFactory;
        $this->argumentSetFactory = $argumentSetFactory;
    }

    /**
     * Convert a FieldValue to an executable FieldDefinition.
     *
     * @return array Configuration array for a \GraphQL\Type\Definition\FieldDefinition
     */
    public function handle(FieldValue $fieldValue): array
    {
        $fieldDefinitionNode = $fieldValue->getField();

        // Directives have the first priority for defining a resolver for a field
        /** @var \Nuwave\Lighthouse\Support\Contracts\FieldResolver|null $resolverDirective */
        $resolverDirective = $this->directiveFactory->createSingleDirectiveOfType($fieldDefinitionNode, FieldResolver::class);
        if ($resolverDirective) {
            $this->fieldValue = $resolverDirective->resolveField($fieldValue);
        } else {
            $this->fieldValue = $fieldValue->useDefaultResolver();
        }

        $fieldMiddleware = $this->passResolverArguments(
            $this->directiveFactory->createAssociatedDirectivesOfType($fieldDefinitionNode, FieldMiddleware::class)
        );
        $this->validationErrorBuffer = (new ErrorBuffer)->setErrorType('validation');

        $resolverWithMiddleware = $this->pipeline
            ->send($this->fieldValue)
            ->through($fieldMiddleware)
            ->via('handleField')
            ->then(function (FieldValue $fieldValue): FieldValue {
                return $fieldValue;
            })
            ->getResolver();

        $argumentMap = $this->argumentFactory->toTypeMap(
            $this->fieldValue->getField()->arguments
        );

        $this->fieldValue->setResolver(
            function () use ($argumentMap, $resolverWithMiddleware) {
                $this->setResolverArguments(...func_get_args());

                foreach ($argumentMap as $name => $argumentValue) {
                    $this->handleArgDirectivesRecursively(
                        $argumentValue['type'],
                        $argumentValue['astNode'],
                        [$name]
                    );
                }

                // Recurse down the given args and apply ArgDirectives
                $this->runArgDirectives();

                // Now that we are finished with all argument based validation,
                // we flush the validation error buffer
                $this->flushValidationErrorBuffer();

                $argumentSet = $this->argumentSetFactory->fromResolveInfo($this->args, $this->resolveInfo);
                $modifiedArgumentSet = $argumentSet
                    ->spread()
                    ->rename();
                $this->resolveInfo->argumentSet = $modifiedArgumentSet;

                return $resolverWithMiddleware(
                    $this->root,
                    $modifiedArgumentSet->toArray(),
                    $this->context,
                    $this->resolveInfo
                );
            }
        );

        // To see what is allowed here, look at the validation rules in
        // GraphQL\Type\Definition\FieldDefinition::getDefinition()
        return [
            'name' => $fieldDefinitionNode->name->value,
            'type' => $this->fieldValue->getReturnType(),
            'args' => $argumentMap,
            'resolve' => $this->fieldValue->getResolver(),
            'description' => data_get($fieldDefinitionNode->description, 'value'),
            'complexity' => $this->fieldValue->getComplexity(),
            'deprecationReason' => $this->fieldValue->getDeprecationReason(),
        ];
    }

    /**
     * Handle the ArgMiddleware.
     *
     * @param  mixed[]  $argumentPath
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

        $directives = $this->passResolverArguments(
            $this->directiveFactory->createAssociatedDirectivesOfType($astNode, ArgDirective::class)
        );

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
     * @param  \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\Directive>  $directives
     * @param  mixed[]  $argumentPath
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
     * @param  mixed[]  $argumentPath
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
            if ($directive instanceof ProvidesRules) {
                $validators = $this->gatherValidationDirectives($directives);

                $validators->push($directive);
                foreach ($validators as $validator) {
                    // We gather the rules from all arguments and then run validation in one full swoop
                    $this->rules = array_merge_recursive($this->rules, $validator->rules());
                    $this->messages = array_merge_recursive($this->messages, $validator->messages());
                }

                break;
            }

            if ($directive instanceof ArgTransformerDirective && $this->argValueExists($argumentPath)) {
                $this->setArgValue(
                    $argumentPath,
                    $directive->transform($this->argValue($argumentPath))
                );
            }
        }

        // If directives remain, snapshot the state that we are in now
        // to allow resuming after validation has run
        if ($directives->isNotEmpty()) {
            $this->handleArgDirectivesSnapshots[] = [$astNode, $argumentPath, $directives];
        }
    }

    protected function gatherValidationDirectives(Collection &$directives): Collection
    {
        // We only get the validator directives that are directly following on the latest validator
        // directive. If we'd get all validator directives and merge them together, it wouldn't
        // be possible anymore to mutate the input with argument transformer directives.
        $validators = new Collection();
        while ($directive = $directives->first()) {
            if ($directive instanceof ProvidesRules) {
                $validators->push($directives->shift());
            } else {
                return $validators;
            }
        }

        return $validators;
    }

    /**
     * @param  string[]  $argumentPath
     */
    protected function argValueExists(array $argumentPath): bool
    {
        return Arr::has($this->args, implode('.', $argumentPath));
    }

    /**
     * @param  string[]  $argumentPath
     * @return mixed[]
     */
    protected function setArgValue(array $argumentPath, $value): array
    {
        return Arr::set($this->args, implode('.', $argumentPath), $value);
    }

    /**
     * @param  string[]  $argumentPath
     */
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
     */
    protected function validateArgs(): void
    {
        if (! $this->rules) {
            return;
        }

        /** @var \Nuwave\Lighthouse\Execution\GraphQLValidator $validator */
        $validator = $this->validationFactory->make(
            $this->args,
            $this->rules,
            $this->messages,
            // The presence of those custom attributes ensures we get a GraphQLValidator
            [
                'root' => $this->root,
                'context' => $this->context,
                'resolveInfo' => $this->resolveInfo,
            ]
        );

        if ($validator->fails()) {
            $this->addValidationErrorsToBuffer(
                $validator->errors()->getMessages()
            );
        }

        // reset rules and messages
        $this->rules = [];
        $this->messages = [];
    }

    /**
     * Continue evaluating the arg directives after validation has run.
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

    protected function addValidationErrorsToBuffer(array $validationErrors): void
    {
        foreach ($validationErrors as $key => $errorMessages) {
            foreach ($errorMessages as $errorMessage) {
                $this->validationErrorBuffer->push($errorMessage, $key);
            }
        }
    }

    protected function flushValidationErrorBuffer(): void
    {
        $path = implode(
            '.',
            $this->resolveInfo()->path
        );

        $this->validationErrorBuffer->flush(
            "Validation failed for the field [$path]."
        );
    }
}
