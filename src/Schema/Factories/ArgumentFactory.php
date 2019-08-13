<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

class ArgumentFactory
{
    /**
     * Convert argument definition to type.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\ArgumentValue  $argumentValue
     * @return array
     */
    public function handle(ArgumentValue $argumentValue): array
    {
        $definition = $argumentValue->getAstNode();

        $argumentType = $argumentValue->getType();

        $fieldArgument = [
            'name' => $argumentValue->getName(),
            'description' => data_get($definition->description, 'value'),
            'type' => $argumentType,
            'astNode' => $definition,
        ];

        if ($defaultValue = $definition->defaultValue) {
            $fieldArgument += [
                'defaultValue' => ASTHelper::defaultValueForArgument($defaultValue, $argumentType),
            ];
        }

        // Add any dynamically declared public properties of the FieldArgument
        $fieldArgument += get_object_vars($argumentValue);

        // Used to construct a FieldArgument class
        return $fieldArgument;
    }
}
