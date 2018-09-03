<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Pipeline;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Execution\GraphQLValidator;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;

class FieldFactory
{
    /**
     * Convert a FieldValue to an executable FieldDefinition.
     *
     * @param FieldValue $fieldValue
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     *
     * @return array Configuration array for a FieldDefinition
     */
    public function handle(FieldValue $fieldValue): array
    {
        $fieldValue->setType(
            NodeResolver::resolve($fieldValue->getField()->type)
        );

        $initialResolver = $this->hasResolverDirective($fieldValue)
            ? $this->useResolverDirective($fieldValue)
            : $this->defaultResolver($fieldValue);

        $inputValueDefinitions = $this->getInputValueDefinitions($fieldValue);
        $resolverWithAdditionalArgs = $this->injectAdditionalArgs($initialResolver, $fieldValue->getAdditionalArgs());
        $resolverWithValidation = $this->wrapResolverWithValidation($resolverWithAdditionalArgs, $inputValueDefinitions);

        $fieldValue->setResolver($resolverWithValidation);

        $resolverWithMiddleware = app(Pipeline::class)
            ->send($fieldValue)
            ->through(app(DirectiveRegistry::class)->fieldMiddleware($fieldValue->getField()))
            ->via('handleField')
            ->then(function (FieldValue $fieldValue) {
                return $fieldValue;
            })
            ->getResolver();

        // To see what is allowed here, look at the validation rules in
        // GraphQL\Type\Definition\FieldDefinition::getDefinition()
        return [
            'name' => $fieldValue->getFieldName(),
            'type' => $fieldValue->getType(),
            'args' => $inputValueDefinitions->toArray(),
            'resolve' => $resolverWithMiddleware,
            'description' => $fieldValue->getDescription(),
            'complexity' => $fieldValue->getComplexity(),
        ];
    }

    /**
     * Check if field has a resolver directive.
     *
     * @param FieldValue $value
     *
     * @return bool
     */
    protected function hasResolverDirective(FieldValue $value): bool
    {
        return app(DirectiveRegistry::class)
            ->hasResolver($value->getField());
    }

    /**
     * Use directive resolver to transform field.
     *
     * @param FieldValue $value
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     *
     * @return \Closure
     */
    protected function useResolverDirective(FieldValue $value): \Closure
    {
        return app(DirectiveRegistry::class)
            ->fieldResolver($value->getField())
            ->resolveField($value)
            ->getResolver();
    }

    /**
     * Get default field resolver.
     *
     * @param FieldValue $fieldValue
     *
     * @return \Closure
     */
    protected function defaultResolver(FieldValue $fieldValue): \Closure
    {
        switch ($fieldValue->getNodeName()) {
            case 'Mutation':
                return $this->rootOperationResolver($fieldValue->getFieldName(), 'mutations');
            case 'Query':
                return $this->rootOperationResolver($fieldValue->getFieldName(), 'queries');
            default:
                return \Closure::fromCallable([\GraphQL\Executor\Executor::class, 'defaultFieldResolver']);
        }
    }

    /**
     * Get the default resolver for a field of the root operation types.
     *
     * @param string $fieldName
     * @param string $rootOperationType One of queries|mutations
     *
     * @return \Closure
     */
    protected function rootOperationResolver(string $fieldName, string $rootOperationType): \Closure
    {
        return function ($obj, array $args, $context = null, $info = null) use ($fieldName, $rootOperationType) {
            $class = config("lighthouse.namespaces.{$rootOperationType}").'\\'.studly_case($fieldName);

            return (new $class($obj, $args, $context, $info))->resolve();
        };
    }

    /**
     * Wrap the resolver by injecting additional arg values.
     *
     * @param \Closure $resolver
     * @param array    $additionalArgs
     *
     * @return \Closure
     */
    protected function injectAdditionalArgs(\Closure $resolver, array $additionalArgs): \Closure
    {
        return function () use ($resolver, $additionalArgs) {
            $resolverArgs = func_get_args();
            // The second argument of resolvers are the argument values
            $resolverArgs[1] = array_merge($resolverArgs[1], $additionalArgs);

            return call_user_func_array($resolver, $resolverArgs);
        };
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
        return collect(data_get($fieldValue->getField(), 'arguments', []))
            ->mapWithKeys(function (InputValueDefinitionNode $inputValueDefinition) use ($fieldValue) {
                $argValue = app(ValueFactory::class)->arg($fieldValue, $inputValueDefinition);

                return [$argValue->getArgName() => (new ArgumentFactory())->handle($argValue)];
            });
    }

    /**
     * Wrap field resolver function with validation logic.
     *
     * This has to happen as part of the field resolution, because we might have
     * deeply nested input values and we can not generate the rules upfront.
     *
     * @param \Closure $resolver
     * @param Collection $inputValueDefinitions
     *
     * @return \Closure
     */
    protected function wrapResolverWithValidation(\Closure $resolver, Collection $inputValueDefinitions): \Closure
    {
        return function ($rootValue, $inputArgs, $context = null, ResolveInfo $resolveInfo = null) use ($resolver, $inputValueDefinitions) {
            $inputArgs = $this->resolveArgs($inputArgs, $inputValueDefinitions);

            list($rules, $messages) = $this->getRulesAndMessages(
                $rootValue,
                $inputArgs,
                $context,
                $resolveInfo,
                $inputValueDefinitions
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

            return call_user_func_array($resolver, [$rootValue, $inputArgs, $context, $resolveInfo]);
        };
    }

    /**
     * Arguments may have resolves defined upon them.
     *
     * This iterates through them and ensures they are called.
     *
     * @param array      $inputArguments
     * @param Collection<array> $argumentValues
     *
     * @return array
     */
    protected function resolveArgs(array $inputArguments, Collection $inputValueDefinitions): array
    {
        $resolvers = $inputValueDefinitions->filter(function (array $inputValueDefinition) {
            return array_has($inputValueDefinition, 'resolve');
        });

        if ($resolvers->isEmpty()) {
            return $inputArguments;
        }

        return collect($inputArguments)
            ->map(function ($value, string $key) use ($resolvers) {
                return $resolvers->has($key)
                    ? $resolvers->get($key)['resolve']($value)
                    : $value;
            })
            ->toArray();
    }

    /**
     * Get rules for field.
     *
     * @param mixed            $rootValue
     * @param array            $inputArgs
     * @param mixed            $context
     * @param ResolveInfo|null $resolveInfo
     * @param Collection       $inputValueDefinitions
     *
     * @return array [$rules, $messages]
     */
    public function getRulesAndMessages(
        $rootValue,
        $inputArgs,
        $context,
        ResolveInfo $resolveInfo = null,
        Collection $inputValueDefinitions
    ): array {
        $resolveArgs = [$rootValue, $inputArgs, $context, $resolveInfo];

        $rules = $inputValueDefinitions
            ->map(function (array $inputValueDefinition, $key) use ($resolveArgs) {
                $rules = data_get($inputValueDefinition, 'rules');

                if (! $rules) {
                    return null;
                }

                $rules = is_callable($rules)
                    ? call_user_func_array($inputValueDefinition['rules'], $resolveArgs)
                    : $rules;

                return $rules;
            })
            ->filter();

        $messages = $inputValueDefinitions->pluck('messages')->collapse();

        // Rules are applied to the fields which are on one of the root operation types.
        $parentOperationType = data_get($resolveInfo, 'parentType.name');
        if ('Mutation' === $parentOperationType || 'Query' === $parentOperationType) {
            $documentAST = graphql()->documentAST();
            $nestedValidation = (new RuleFactory())->build(
                $documentAST,
                $documentAST->objectTypeDefinition($parentOperationType),
                $inputArgs,
                $resolveInfo->fieldName
            );

            $rules = $rules->merge(array_get($nestedValidation, 'rules', []));
            $messages = $messages->merge(array_get($nestedValidation, 'messages', []));
        }

        return [ $rules->toArray(), $messages->toArray(), ];
    }
}
