<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Exceptions\ValidationError;

class FieldFactory
{
    /**
     * Convert a FieldValue to an executable FieldDefinition.
     *
     * @param FieldValue $fieldValue
     *
     * @return array Configuration array for a FieldDefinition
     * @throws \Nuwave\Lighthouse\Support\Exceptions\DirectiveException
     */
    public function handle(FieldValue $fieldValue): array
    {
        $fieldValue->setType(
            NodeResolver::resolve($fieldValue->getField()->type)
        );

        $initialResolver = $this->hasResolverDirective($fieldValue)
            ? $this->useResolverDirective($fieldValue)
            : $this->defaultResolver($fieldValue);


        $args = $this->getArgDefinitions($fieldValue);

        $resolverWithAdditionalArgs = $this->injectAdditionalArgs($initialResolver, $fieldValue->getAdditionalArgs());

        $resolverWithValidation = $this->wrapResolverWithValidation($resolverWithAdditionalArgs, $args);

        $fieldValue->setResolver($resolverWithValidation);

        $resolverWithMiddleware = graphql()->directives()->fieldMiddleware($fieldValue->getField())
            ->reduce(function (FieldValue $fieldValue, FieldMiddleware $middleware) {
                return $middleware->handleField($fieldValue);
            }, $fieldValue)
            ->getResolver();


        // To see what is allowed here, look at the validation rules in
        // GraphQL\Type\Definition\FieldDefinition::getDefinition()
        return [
            'name' => $fieldValue->getFieldName(),
            'type' => $fieldValue->getType(),
            'args' => $args->toArray(),
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
    protected function hasResolverDirective(FieldValue $value)
    {
        return graphql()->directives()->hasResolver($value->getField());
    }

    /**
     * Use directive resolver to transform field.
     *
     * @param FieldValue $value
     *
     * @return \Closure
     * @throws \Nuwave\Lighthouse\Support\Exceptions\DirectiveException
     */
    protected function useResolverDirective(FieldValue $value)
    {
        return graphql()->directives()->fieldResolver($value->getField())
            ->resolveField($value)->getResolver();
    }

    /**
     * Get default field resolver.
     *
     * @param FieldValue $fieldValue
     *
     * @return \Closure
     */
    protected function defaultResolver(FieldValue $fieldValue)
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
    protected function rootOperationResolver(string $fieldName, string $rootOperationType)
    {
        return function ($obj, array $args, $context = null, $info = null) use ($fieldName, $rootOperationType) {
            $class = config("lighthouse.namespaces.{$rootOperationType}") . '\\' . studly_case($fieldName);

            return (new $class($obj, $args, $context, $info))->resolve();
        };
    }

    /**
     * Wrap the resolver by injecting additional arg values.
     *
     * @param \Closure $resolver
     * @param array $additionalArgs
     *
     * @return \Closure
     */
    protected function injectAdditionalArgs(\Closure $resolver, array $additionalArgs)
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
     * @return \Illuminate\Support\Collection
     */
    protected function getArgDefinitions(FieldValue $fieldValue)
    {
        return collect(data_get($fieldValue->getField(), 'arguments', []))
            ->mapWithKeys(function (InputValueDefinitionNode $inputValueDefinition) use ($fieldValue) {
                $argValue = new ArgumentValue($fieldValue, $inputValueDefinition);

                return [$argValue->getArgName() => (new ArgumentFactory())->handle($argValue)];
            });
    }

    protected function wrapResolverWithValidation(\Closure $resolver, $inputValueDefinitions)
    {
        return function ($rootValue, $inputArgs, $context = null, $resolveInfo = null) use ($resolver, $inputValueDefinitions) {
            $inputArgs = $this->resolveArgs($inputArgs, $inputValueDefinitions);

            $rules = $this->getRules($rootValue, $inputArgs, $context, $resolveInfo, $inputValueDefinitions);

            if (sizeof($rules)) {
                $validator = validator($inputArgs, $rules);
                if ($validator->fails()) {
                    throw with(new ValidationError('validation'))->setValidator($validator);
                }
            }

            return call_user_func_array($resolver, [$rootValue, $inputArgs, $context, $resolveInfo]);
        };
    }

    /**
     * Resolve argument(s).
     *
     * @param array $inputArguments
     *
     * @return array
     */
    protected function resolveArgs(array $inputArguments, Collection $argumentValues)
    {
        $resolvers = $argumentValues->filter(function ($arg) {
            return array_has($arg, 'resolve');
        });

        if ($resolvers->isEmpty()) {
            return $inputArguments;
        }

        return collect($inputArguments)
            ->map(function ($arg, $key) use ($resolvers) {
                return $resolvers->has($key)
                    ? $resolvers->get($key)['resolve']($arg)
                    : $arg;
            })
            ->toArray();
    }

    /**
     * Get rules for field.
     *
     * @return array
     */
    public function getRules($rootValue, $inputArgs, $context, ResolveInfo $resolveInfo = null, Collection $inputValueDefinitions): array
    {
        $resolveArgs = [$rootValue, $inputArgs, $context, $resolveInfo];

        $rules = $inputValueDefinitions
            ->map(function ($inputValueDefinition) use ($resolveArgs) {
                $rules = data_get($inputValueDefinition, 'rules');

                if (!$rules) {
                    return;
                }

                return is_callable($rules)
                    ? call_user_func_array($inputValueDefinition['rules'], $resolveArgs)
                    : $rules;
            })
            ->filter();

        // Rules are applied to the fields which are on the root operation types
        // Nested fields are excluded because they are validated as part of the root field
        $parentOperationType = data_get($resolveInfo, 'parentType.name');
        if ('Mutation' === $parentOperationType || 'Query' === $parentOperationType) {
            $rules = $rules->merge((new RuleFactory())->build(
                graphql()->documentAST(), $inputArgs, $resolveInfo->fieldName, $parentOperationType
            ));
        }

        return $rules->toArray();
    }
}
