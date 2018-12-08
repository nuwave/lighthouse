<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Closure;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Pipeline;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\GraphQLValidator;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class FieldFactory
{
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
        $fieldDefinition = $fieldValue->getField();

        if ($fieldResolver = $this->directiveRegistry->fieldResolver($fieldDefinition)) {
            $fieldValue = $fieldResolver->resolveField($fieldValue);
        }

        $initialResolver = $fieldValue->getResolver();

        $inputValueDefinitions = $this->getInputValueDefinitions($fieldValue);
        $resolver = $this->injectAdditionalArgs(
            $initialResolver,
            $fieldValue->getAdditionalArgs()
        );


        // No need to do transformation/validation of the arguments
        // if there are no arguments defined for the field
        if ($inputValueDefinitions->isNotEmpty()) {
            $resolver = $this->wrapResolverByTransformingArgs($resolver, $inputValueDefinitions);
            $resolver = $this->wrapResolverWithValidation($resolver, $inputValueDefinitions);
        }

        $fieldValue->setResolver($resolver);

        $resolverWithMiddleware = $this->pipeline
            ->send($fieldValue)
            ->through(
                $this->directiveRegistry->fieldMiddleware($fieldDefinition)
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
            'name' => $fieldDefinition->name->value,
            'type' => $fieldValue->getReturnType(),
            'args' => $inputValueDefinitions->toArray(),
            'resolve' => $resolverWithMiddleware,
            'description' => data_get($fieldDefinition->description, 'value'),
            'complexity' => $fieldValue->getComplexity(),
        ];
    }

    /**
     * Get collection of field arguments.
     *
     * @param FieldValue $fieldValue
     *
     * @return Collection
     */
    protected function getInputValueDefinitions(FieldValue $fieldValue): Collection
    {
        return collect($fieldValue->getField()->arguments)
            ->mapWithKeys(function (InputValueDefinitionNode $inputValueDefinition) use ($fieldValue) {
                $argValue = new ArgumentValue($inputValueDefinition, $fieldValue);

                return [
                    $inputValueDefinition->name->value => $this->argumentFactory->handle($argValue),
                ];
            });
    }

    /**
     * Wrap the resolver by injecting additional arg values.
     *
     * @param \Closure $resolver
     * @param array    $additionalArgs
     *
     * @return \Closure
     */
    protected function injectAdditionalArgs(Closure $resolver, array $additionalArgs): Closure
    {
        return function () use ($resolver, $additionalArgs) {
            $resolverArgs = func_get_args();
            // The second argument of resolvers are the argument values
            $resolverArgs[1] = array_merge($resolverArgs[1], $additionalArgs);

            return call_user_func_array($resolver, $resolverArgs);
        };
    }

    /**
     * Perform transformations on the arguments given to the field.
     *
     * For example, an argument may be encrypted before reaching the final resolver.
     *
     * @param \Closure          $resolver
     * @param Collection<array> $inputValueDefinitions
     *
     * @return \Closure
     */
    protected function wrapResolverByTransformingArgs(Closure $resolver, Collection $inputValueDefinitions): Closure
    {
        return function ($rootValue, $inputArgs, $context = null, ResolveInfo $resolveInfo) use ($resolver, $inputValueDefinitions) {
            $inputArgs = collect($inputArgs)
                ->map(function ($value, string $key) use ($inputValueDefinitions) {
                    $definition = $inputValueDefinitions->get($key);

                    return collect($definition['transformers'])
                        ->reduce(
                            function ($value, Closure $transformer) {
                                return $transformer($value);
                            },
                            $value
                        );
                })
                ->toArray();

            return $resolver($rootValue, $inputArgs, $context, $resolveInfo);
        };
    }

    /**
     * Wrap field resolver function with validation logic.
     *
     * This has to happen as part of the field resolution, because we might have
     * deeply nested input values and we can not generate the rules upfront.
     *
     * @param \Closure          $resolver
     * @param Collection<array> $inputValueDefinitions
     *
     * @return \Closure
     */
    protected function wrapResolverWithValidation(Closure $resolver, Collection $inputValueDefinitions): Closure
    {
        return function ($rootValue, $inputArgs, $context = null, ResolveInfo $resolveInfo) use ($resolver, $inputValueDefinitions) {
            list($rules, $messages) = RuleFactory::build(
                $resolveInfo->fieldName,
                $resolveInfo->parentType->name,
                $inputArgs,
                graphql()->documentAST()
            );

            if (count($rules) > 0) {
                /** @var GraphQLValidator $validator */
                $validator = validator(
                    $inputArgs,
                    $rules,
                    $messages,
                    [
                        'root' => $rootValue,
                        'context' => $context,
                        // This makes it so that we get an instance of our own Validator class
                        'resolveInfo' => $resolveInfo,
                    ]
                );

                $validator->validate();
            }

            return $resolver($rootValue, $inputArgs, $context, $resolveInfo);
        };
    }
}
