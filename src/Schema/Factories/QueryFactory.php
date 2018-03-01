<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\Resolvers\QueryResolver;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

class QueryFactory
{
    /**
     * Convert field definition to query.
     *
     * @param FieldDefinitionNode $query
     * @param Node                $node
     *
     * @return array
     */
    public static function resolve(FieldDefinitionNode $query, Node $node)
    {
        $value = FieldValue::init(schema()->instance('Query'), $node, $query, '');
        $field = directives()->hasResolver($query)
            ? directives()->fieldResolver($query)->handle($value)
            : QueryResolver::resolve($query, self::resolver($query));

        $field['resolve'] = directives()->fieldMiddleware($query)
            ->reduce(function ($resolver, $middleware) use ($query) {
                return $middleware->handle($query, $resolver);
            }, $field['resolve']);

        return $field;
    }

    /**
     * Attempt to get resolver for mutation name.
     *
     * @param FieldDefinitionNode $query
     *
     * @return \Closure
     */
    public static function resolver(FieldDefinitionNode $query)
    {
        $class = config('lighthouse.namespaces.queries').'\\'.studly_case($query->name->value);

        $query = app($class);

        return (new \ReflectionClass($query))->getMethod('resolve')->getClosure($query);
    }
}
