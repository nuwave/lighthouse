<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Illuminate\Support\Collection;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Exceptions\ValidationError;

class FieldFactory
{
    /**
     * Convert a FieldValue to an executable FieldDefinition.
     *
     * @param FieldValue $fieldValue
     *
     * @throws \Nuwave\Lighthouse\Support\Exceptions\DirectiveException
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

        $argDefinitions = $this->getArgDefinitions($fieldValue);
        $resolverWithAdditionalArgs = $this->injectAdditionalArgs($initialResolver, $fieldValue->getAdditionalArgs());
        $resolverWithValidation = $this->wrapResolverWithValidation($resolverWithAdditionalArgs, $argDefinitions);

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
            'args' => ArgumentFactory::convertToExecutable($argDefinitions),
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
        return graphql()->directives()->hasFieldResolver($value->getField());
    }

    /**
     * Use directive resolver to transform field.
     *
     * @param FieldValue $value
     *
     * @throws \Nuwave\Lighthouse\Support\Exceptions\DirectiveException
     *
     * @return \Closure
     */
    protected function useResolverDirective(FieldValue $value): \Closure
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
    protected function getArgDefinitions(FieldValue $fieldValue): Collection
    {
        return collect(data_get($fieldValue->getField(), 'arguments', []))
            ->mapWithKeys(function (InputValueDefinitionNode $inputValueDefinition) use ($fieldValue) {
                $argumentValue = new ArgumentValue($fieldValue, $inputValueDefinition);

                return [$argumentValue->getArgName() => ArgumentFactory::handle($argumentValue)];
            });
    }

    /**
     * Wrap field resolver function w/ validation logic.
     *
     * @param \Closure   $resolver
     * @param Collection $inputValueDefinitions
     *
     * @return \Closure
     */
    protected function wrapResolverWithValidation(\Closure $resolver, Collection $inputValueDefinitions): \Closure
    {
        return function ($rootValue, $inputArgs, $context = null, $resolveInfo = null) use ($resolver, $inputValueDefinitions) {
            $inputArgs = $this->resolveArgs($inputArgs, $inputValueDefinitions);
            $rules = $this->getRules(
                $rootValue,
                $inputArgs,
                $context,
                $resolveInfo,
                $inputValueDefinitions
            );

            if (sizeof(array_get($rules, 'rules', []))) {
                $validator = validator(
                    $inputArgs,
                    array_get($rules, 'rules'),
                    array_get($rules, 'messages', []),
                    [
                        'root' => $rootValue,
                        'context' => $context,
                        'resolveInfo' => $resolveInfo,
                    ]
                );

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
     * @param array      $inputArguments
     * @param Collection $argumentValues
     *
     * @return array
     */
    protected function resolveArgs(array $inputArguments, Collection $argumentValues): array
    {
        return collect($inputArguments)
            ->map(function ($arg, string $key) use ($argumentValues) {
                $resolver = $argumentValues->get($key)->getResolver();

                return $resolver
                    ? $resolver($arg)
                    : $arg;
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
     * @return array
     */
    public function getRules(
        $rootValue,
        array $inputArgs,
        $context,
        ResolveInfo $resolveInfo = null,
        Collection $inputValueDefinitions
    ): array {
        $resolveArgs = [$rootValue, $inputArgs, $context, $resolveInfo];

        $validation = $inputValueDefinitions
            ->map(function (ArgumentValue $inputValueDefinition, string $key) use ($resolveArgs) {
                $rules = $inputValueDefinition->getRules();

                if (! $rules) {
                    return;
                }

                // TODO why is this here
                $rules = is_callable($rules)
                    ? call_user_func_array($inputValueDefinition['rules'], $resolveArgs)
                    : $rules;

                return [
                    'rules' => [$key => $rules],
                    'messages' => $inputValueDefinition->getMessages() ?? [],
                ];
            })
            ->filter()
            ->values();

        $rules = $validation->flatMap(function ($validation) {
            return $validation['rules'];
        });
        $messages = $validation->flatMap(function ($validation) {
            return $validation['messages'];
        });

        // Rules are applied to the fields which are on one of the root operation types.
        // Nested fields are excluded because they are validated as part of the root field.
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

        return [
            'rules' => $rules->toArray(),
            'messages' => $messages->toArray(),
        ];
    }
}
