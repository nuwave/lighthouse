<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Closure;
use Illuminate\Support\Collection;
use GraphQL\Type\Definition\NonNull;
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
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\HasErrorBuffer;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath;
use Nuwave\Lighthouse\Support\Contracts\ProvidesResolver;
use Nuwave\Lighthouse\Support\Traits\HasResolverArguments;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgValidationDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver;

class FieldFactory
{
    use HasResolverArguments;

    /**
     * @var \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    protected $fieldValue;

    /**
     * @var \Nuwave\Lighthouse\Execution\QueryFilter
     */
    protected $queryFilter;

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
     * @var array
     */
    protected $currentRules = [];

    /**
     * @var array
     */
    protected $currentMessages = [];

    /**
     * @var \Nuwave\Lighthouse\Execution\ErrorBuffer
     */
    protected $currentValidationErrorBuffer;

    /**
     * @var \Nuwave\Lighthouse\Schema\Values\ArgumentValue
     */
    protected $currentArgumentValueInstance;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\ArgDirective[]
     */
    protected $currentHandlerArgsOfArgDirectivesAfterValidationDirective = [];

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\ProvidesResolver
     */
    protected $providesResolver;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver
     */
    protected $providesSubscriptionResolver;

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
                $this->directiveFactory->createFieldMiddleware($fieldDefinitionNode)
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
        return [
            'name' => $fieldDefinitionNode->name->value,
            'type' => $this->fieldValue->getReturnType(),
            'args' => $this->getInputValueDefinitions($argumentValues),
            'resolve' => $resolverWithMiddleware,
            'description' => data_get($fieldDefinitionNode->description, 'value'),
            'complexity' => $this->fieldValue->getComplexity(),
            'deprecationReason' => $this->fieldValue->getDeprecationReason(),
        ];
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
     * Call `ArgMiddleware::handleArgument` at the resolving time.
     *
     * This may be used to transform the arguments, log them or do anything else
     * before they reach the final resolver.
     *
     * @param  \Closure  $resolver
     * @param  \Illuminate\Support\Collection<ArgumentValue>  $argumentValues
     * @return \Closure
     */
    public function decorateResolverWithArgs(Closure $resolver, Collection $argumentValues): Closure
    {
        return function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver, $argumentValues) {
            $this->currentValidationErrorBuffer = app(ErrorBuffer::class)->setErrorType('validation');

            $this->setResolverArguments($root, $args, $context, $resolveInfo);

            $argumentValues->each(
                function (ArgumentValue $argumentValue) use (&$args): void {
                    $this->currentArgumentValueInstance = $argumentValue;

                    $noValuePassedForThisArgument = ! array_key_exists($argumentValue->getName(), $args);

                    // because we are passing by reference, we need a variable to contain the null value.
                    if ($noValuePassedForThisArgument) {
                        $argValue = new NoValue;
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

            // Validate arguments placed before `ValidationDirective`s
            $this->validateArgumentsBeforeValidationDirectives($root, $args, $context, $resolveInfo);

            // Handle `ArgDirective`s after `ValidationDirective`s
            $this->handleArgDirectivesAfterValidationDirectives($root, $args, $context, $resolveInfo);

            // We (ab)use the ResolveInfo as a way of passing down the query filter
            // to the final resolver
            $resolveInfo->queryFilter = $this->queryFilter;

            return $resolver($root, $args, $context, $resolveInfo);
        };
    }

    /**
     * Handle the ArgMiddleware.
     *
     * @param  \GraphQL\Type\Definition\InputType  $type
     * @param  mixed  $argValue
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $astNode
     * @param  mixed[]  $argumentPath
     *
     * @return void
     */
    protected function handleArgWithAssociatedDirectivesRecursively(
        InputType $type,
        &$argValue,
        InputValueDefinitionNode $astNode,
        array $argumentPath
    ): void {
        if ($argValue instanceof NoValue || $argValue === null) {
            // Handle `ListOfType` with associated directives which implement `ArgDirectiveForArray`
            if ($type instanceof ListOfType) {
                $this->handleArgWithAssociatedDirectives($astNode, $argValue, $argumentPath, true);
                // No need to consider the rules for the elements of the list, since we know it is empty
                return;
            }

            // Handle `InputObjectType` and all other leaf types
            $this->handleArgWithAssociatedDirectives($astNode, $argValue, $argumentPath, false);

            return;
        }

        if ($type instanceof NonNull) {
            $this->handleArgWithAssociatedDirectivesRecursively($type->getWrappedType(), $argValue, $astNode, $argumentPath);

            return;
        }

        if ($type instanceof InputObjectType) {
            (new Collection($type->getFields()))
                ->each(
                    function (InputObjectField $field) use ($argumentPath, &$argValue): void {
                        $noValuePassedForThisArgument = ! array_key_exists($field->name, $argValue);

                        // because we are passing by reference, we need a variable to contain the null value.
                        if ($noValuePassedForThisArgument) {
                            $value = new NoValue;
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
            $argValue = $this->handleArgWithAssociatedDirectives($astNode, $argValue, $argumentPath, true);

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
        $argValue = $this->handleArgWithAssociatedDirectives($astNode, $argValue, $argumentPath, false);
    }

    /**
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $astNode
     * @param  mixed  $argValue
     * @param  mixed[]  $argumentPath
     * @param  bool  $argumentIsList
     * @return mixed
     */
    protected function handleArgWithAssociatedDirectives(
        InputValueDefinitionNode $astNode,
        $argValue,
        array $argumentPath,
        bool $argumentIsList
    ) {
        $directives = $this->directiveFactory->createArgDirectives($astNode);

        $isArgDirectiveForArray = function (ArgDirective $directive): bool {
            return $directive instanceof ArgDirectiveForArray;
        };

        $directives = $argumentIsList
            ? $directives->filter($isArgDirectiveForArray)
            : $directives->reject($isArgDirectiveForArray);

        return $this->handleArgDirectives($astNode, $argValue, $argumentPath, $directives);
    }

    /**
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $astNode
     * @param  mixed  $argumentValue
     * @param  mixed[]  $argumentPath
     * @param  \Illuminate\Support\Collection  $directives
     * @return mixed
     */
    protected function handleArgDirectives(
        InputValueDefinitionNode $astNode,
        $argumentValue,
        array $argumentPath,
        Collection $directives
    ) {
        if ($directives->isEmpty()) {
            return $argumentValue;
        }

        $this->prepareDirectives($argumentPath, $directives);

        foreach ($directives as $directive) {
            // Remove the directive from the list to avoid evaluating
            // the same directive twice
            $directives->shift();

            // Pause the iteration once we hit any directive that has to do
            // with validation. We will resume running through the remaining
            // directives later, after we completed validation
            if ($directive instanceof ArgValidationDirective) {
                $this->collectRulesAndMessages($directive);
                break;
            }

            if ($directive instanceof ArgTransformerDirective && ! $argumentValue instanceof NoValue) {
                $argumentValue = $directive->transform($argumentValue);
            }

            if ($directive instanceof ArgFilterDirective) {
                $this->injectArgumentFilter($directive, $astNode);
            }
        }

        // If directives remain, snapshot the state that we are in now
        // to allow resuming after validation has run
        if ($directives->count()) {
            $this->currentHandlerArgsOfArgDirectivesAfterValidationDirective[] = [$astNode, $argumentValue, $argumentPath, $directives];
        }

        return $argumentValue;
    }

    /**
     * @param  mixed[]  $argumentPath
     * @param  \Illuminate\Support\Collection  $directives
     *
     * @return void
     */
    protected function prepareDirectives(array $argumentPath, Collection $directives): void
    {
        $directives->each(function (Directive $directive) use ($argumentPath): void {
            if ($directive instanceof HasErrorBuffer) {
                $directive->setErrorBuffer($this->currentValidationErrorBuffer);
            }

            if ($directive instanceof HasArgumentPath) {
                $directive->setArgumentPath($argumentPath);
            }
        });
    }

    /**
     * @param  \Nuwave\Lighthouse\Support\Contracts\ArgValidationDirective  $directive
     * @return void
     */
    protected function collectRulesAndMessages(ArgValidationDirective $directive): void
    {
        $this->currentRules = array_merge($this->currentRules, $directive->getRules());
        $this->currentMessages = array_merge($this->currentMessages, $directive->getMessages());
    }

    /**
     * @param  \Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective  $argFilterDirective
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $inputValueDefinition
     *
     * @return void
     */
    protected function injectArgumentFilter(ArgFilterDirective $argFilterDirective, InputValueDefinitionNode $inputValueDefinition): void
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
     * @param  mixed[]  $basePath
     * @param  string|int  $pathToBeAdded
     *
     * @return mixed[]
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
     * @param  mixed  $root
     * @param  array  $args
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     *
     * @return void
     */
    protected function validateArgumentsBeforeValidationDirectives($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): void
    {
        if (! $this->currentRules) {
            return;
        }

        $validator = validator(
            $args,
            $this->currentRules,
            $this->currentMessages,
            [
                'root' => $root,
                'context' => $context,
                // This makes it so that we get an instance of our own Validator class
                'resolveInfo' => $resolveInfo,
            ]
        );

        if ($validator->fails()) {
            $messageBag = $validator->errors();
            foreach ($messageBag->getMessages() as $key => $errorMessages) {
                foreach ($errorMessages as $errorMessage) {
                    $this->currentValidationErrorBuffer->push($errorMessage, $key);
                }
            }
        }

        $this->flushErrorBufferIfHasErrors();

        // reset current rules and messages
        $this->currentRules = [];
        $this->currentMessages = [];
    }

    /**
     * @return void
     */
    protected function flushErrorBufferIfHasErrors(): void
    {
        if ($this->currentValidationErrorBuffer->hasErrors()) {
            $this->currentValidationErrorBuffer->flush(
                $this->getValidationErrorMessage()
            );
        }
    }

    /**
     * @param  mixed  $root
     * @param  array  $args
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     *
     * @return void
     */
    protected function handleArgDirectivesAfterValidationDirectives($root, array &$args, GraphQLContext $context, ResolveInfo $resolveInfo): void
    {
        if (! $this->currentHandlerArgsOfArgDirectivesAfterValidationDirective) {
            return;
        }

        // copy
        $currentHandlerArgsOfArgDirectivesAfterValidationDirective = $this->currentHandlerArgsOfArgDirectivesAfterValidationDirective;

        // reset
        $this->currentHandlerArgsOfArgDirectivesAfterValidationDirective = [];

        foreach ($currentHandlerArgsOfArgDirectivesAfterValidationDirective as $handlerArgs) {
            $value = $this->handleArgDirectives(...$handlerArgs);

            if ($value instanceof NoValue) {
                continue;
            }

            $path = implode('.', $handlerArgs[2]);
            data_set($args, $path, $value);
        }

        if ($this->currentHandlerArgsOfArgDirectivesAfterValidationDirective) {
            $this->validateArgumentsBeforeValidationDirectives($root, $args, $context, $resolveInfo);
            $this->handleArgDirectivesAfterValidationDirectives($root, $args, $context, $resolveInfo);
        }
    }
}
