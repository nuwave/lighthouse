<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
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

        // This is the original resolver
        $resolver = $fieldValue->getResolver();

        $inputValueDefinitions = $this->getInputValueDefinitions($fieldValue);

        // Execution order [2].
        // Inject additional args after argMiddleware has been handled
        // which probably be used in the resolver of the parent field
        $resolver = $this->injectAdditionalArgs($resolver, $fieldValue);

        // Execution order [1].
        // No need to do transformation/validation of the arguments
        // if there are no arguments defined for the field
        if ($inputValueDefinitions->isNotEmpty()) {
            $resolver = $this->argumentFactory->handleArgMiddlewareInResolver($resolver, $inputValueDefinitions);
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
     * @param \Closure   $resolver
     * @param FieldValue $fieldValue
     *
     * @return \Closure
     */
    protected function injectAdditionalArgs(\Closure $resolver, FieldValue $fieldValue): \Closure
    {
        return function (...$resolverArgs) use ($resolver, $fieldValue) {
            // The second argument of resolvers are the argument values
            $resolverArgs[1] = array_merge($resolverArgs[1], $fieldValue->getAdditionalArgs());

            return $resolver(...$resolverArgs);
        };
    }
}
