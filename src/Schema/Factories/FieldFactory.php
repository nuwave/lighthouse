<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

class FieldFactory
{
    /**
     * Convert field definition to type.
     *
     * @param FieldValue $value
     *
     * @return array
     */
    public static function convert(FieldValue $value)
    {
        $resolve = directives()->hasResolver($value->getField())
            ? directives()->fieldResolver($value->getField())->handle($value)
            : static::resolver($value->getField());

        return [
            'type' => $value->getType(),
            'description' => $value->getDescription(),
            'resolve' => directives()->fieldMiddleware($value->getField())
                ->reduce(function ($resolver, $middleware) use ($value) {
                    return $middleware->handle($value->getField(), $resolver);
                }, $resolve),
        ];
    }

    /**
     * Get default field resolver.
     *
     * @param FieldDefinitionNode $field
     *
     * @return \Closure
     */
    public static function resolver(FieldDefinitionNode $field)
    {
        return function ($parent, array $args) use ($field) {
            return data_get($parent, $field->name->value);
        };
    }
}
