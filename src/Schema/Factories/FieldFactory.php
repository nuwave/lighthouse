<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Pipeline;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Execution\GraphQLValidator;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Conversion\DefinitionNodeConverter;

class FieldFactory
{
    /** @var DirectiveRegistry */
    protected $directiveRegistry;
    /** @var ValueFactory */
    protected $valueFactory;
    /** @var ArgumentFactory */
    protected $argumentFactory;
    /** @var Pipeline */
    protected $pipeline;
    /** @var DefinitionNodeConverter */
    protected $definitionNodeConverter;

    /**
     * @param DirectiveRegistry $directiveRegistry
     * @param ValueFactory $valueFactory
     * @param ArgumentFactory $argumentFactory
     * @param Pipeline $pipeline
     * @param DefinitionNodeConverter $definitionNodeConverter
     */
    public function __construct(DirectiveRegistry $directiveRegistry, ValueFactory $valueFactory, ArgumentFactory $argumentFactory, Pipeline $pipeline, DefinitionNodeConverter $definitionNodeConverter)
    {
        $this->directiveRegistry = $directiveRegistry;
        $this->valueFactory = $valueFactory;
        $this->argumentFactory = $argumentFactory;
        $this->pipeline = $pipeline;
        $this->definitionNodeConverter = $definitionNodeConverter;
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
    
        $fieldType = $this->definitionNodeConverter->toType(
            $fieldDefinition->type
        );
        $fieldValue->setType($fieldType);

        $initialResolver = $this->directiveRegistry->hasFieldResolver($fieldDefinition)
            ? $this->useResolverDirective($fieldValue)
            : $this->defaultResolver($fieldValue);

        $inputValueDefinitions = $this->getInputValueDefinitions($fieldValue);
        $resolverWithAdditionalArgs = $this->injectAdditionalArgs($initialResolver, $fieldValue->getAdditionalArgs());
        $resolverWithValidation = $this->wrapResolverWithValidation($resolverWithAdditionalArgs, $inputValueDefinitions);

        $fieldValue->setResolver($resolverWithValidation);

        $resolverWithMiddleware = $this->pipeline
            ->send($fieldValue)
            ->through(
                $this->directiveRegistry->fieldMiddleware($fieldDefinition)
            )
            ->via('handleField')
            ->then(function (FieldValue $fieldValue) {
                return $fieldValue;
            })
            ->getResolver();

        // To see what is allowed here, look at the validation rules in
        // GraphQL\Type\Definition\FieldDefinition::getDefinition()
        return [
            'name' => $fieldDefinition->name->value,
            'type' => $fieldType,
            'args' => $inputValueDefinitions->toArray(),
            'resolve' => $resolverWithMiddleware,
            'description' => data_get($fieldDefinition->description, 'value'),
            'complexity' => $fieldValue->getComplexity(),
        ];
    }

    /**
     * Use directive resolver to transform field.
     *
     * @param FieldValue $value
     *
     * @throws DirectiveException
     *
     * @return \Closure
     */
    protected function useResolverDirective(FieldValue $value): \Closure
    {
        return $this->directiveRegistry
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
                return $this->rootOperationResolver(
                    $fieldValue->getFieldName(),
                    'mutations'
                );
            case 'Query':
                return $this->rootOperationResolver(
                    $fieldValue->getFieldName(),
                    'queries'
                );
            default:
                // TODO convert this back once we require PHP 7.1
//                return \Closure::fromCallable(
//                    [\GraphQL\Executor\Executor::class, 'defaultFieldResolver']
//                );
                return function() {
                    return \GraphQL\Executor\Executor::defaultFieldResolver(...func_get_args());
                };
        }
    }

    /**
     * Get the default resolver for a field of the root operation types.
     *
     * @param string $fieldName
     * @param string $rootOperationType One of [queries|mutations]
     *
     * @return \Closure
     */
    protected function rootOperationResolver(string $fieldName, string $rootOperationType): \Closure
    {
        return function ($obj, array $args, $context = null, $info = null) use ($fieldName, $rootOperationType) {
            $class = config("lighthouse.namespaces.{$rootOperationType}").'\\'.studly_case($fieldName);
    
            return resolve($class)->resolve($obj, $args, $context, $info);
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
        return
            collect(
                // TODO remove this wrapping call once Fields are always FieldDefinitions
                data_get($fieldValue->getField(), 'arguments')
            )
            ->mapWithKeys(function (InputValueDefinitionNode $inputValueDefinition) use ($fieldValue) {
                $argValue = $this->valueFactory->arg($fieldValue, $inputValueDefinition);

                return [$inputValueDefinition->name->value => $this->argumentFactory->handle($argValue)];
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
            $inputArgs = $this->transformArgs($inputArgs, $inputValueDefinitions);

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

            return $resolver($rootValue, $inputArgs, $context, $resolveInfo);
        };
    }

    /**
     * Arguments may have transformers defined upon them.
     *
     * This iterates through them and ensures they are called.
     *
     * @param array $inputArguments
     * @param Collection<array> $argumentValues
     *
     * @return array
     */
    protected function transformArgs(array $inputArguments, Collection $inputValueDefinitions): array
    {
        return collect($inputArguments)
            ->map(function($value, $key) use ($inputValueDefinitions){
                $definition = $inputValueDefinitions->get($key);
                
                return collect($definition['transformers'])
                    ->reduce(
                        function($value, \Closure $transformer){
                            return $transformer($value);
                        },
                        $value
                    );
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

        list($nestedRules, $nestedMessages) = RuleFactory::build(
            $resolveInfo->fieldName,
            $resolveInfo->parentType->name,
            $inputArgs,
            graphql()->documentAST()
        );

        $rules = $rules->merge($nestedRules);
        $messages = $messages->merge($nestedMessages);

        return [ $rules->toArray(), $messages->toArray(), ];
    }
}
