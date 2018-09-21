<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

class ArgumentFactory
{
    /** @var DirectiveRegistry */
    protected $directiveRegistry;
    /** @var Pipeline */
    protected $pipeline;

    /**
     * @param DirectiveRegistry $directiveRegistry
     * @param Pipeline $pipeline
     */
    public function __construct(DirectiveRegistry $directiveRegistry, Pipeline $pipeline)
    {
        $this->directiveRegistry = $directiveRegistry;
        $this->pipeline = $pipeline;
    }

    /**
     * Convert argument definition to type.
     *
     * @param ArgumentValue $value
     *
     * @return array
     */
    public function handle(ArgumentValue $value): array
    {
        $definition = $value->getAstNode();
        /** @var ArgumentValue $value */
        $value = $this->pipeline
            ->send($value)
            ->through(
                $this->directiveRegistry->argMiddleware($definition)
            )
            ->via('handleArgument')
            ->then(function (ArgumentValue $value) {
                return $value;
            });
        
        $fieldArgument = [
            'name' => $definition->name->value,
            'description' => data_get($definition->description, 'value'),
            'type' => $value->getType(),
            'astNode' => $definition,
            'transformers' => $value->getTransformers()
        ];
        
        if($defaultValue = $definition->defaultValue){
            $fieldArgument += [
                'defaultValue' => $defaultValue
            ];
        }
        
        // Add any dynamically declared public properties of the FieldArgument
        $fieldArgument += get_object_vars($value);
        
        // Used to construct a FieldArgument class
        return $fieldArgument;
    }
}
