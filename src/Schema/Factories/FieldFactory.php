<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

class FieldFactory
{
    /**
     * Convert field definition to type.
     *
     * @param FieldDefinitionNode $field
     * @param Node                $node
     *
     * @return array
     */
    public static function convert(FieldDefinitionNode $field, Node $node)
    {
        $resolve = directives()->hasResolver($field)
            ? directives()->fieldResolver($field)->handle(FieldValue::init($node, $field))
            : static::resolver($field);

        return directives()->fieldMiddleware($field)
            ->reduce(function ($resolver, $middleware) use ($field) {
                return $middleware->handle($field, $resolver);
            }, $resolve);
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
