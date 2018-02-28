<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\Resolvers\QueryResolver;

class QueryFactory
{
    /**
     * Convert field definition to query.
     *
     * @param FieldDefinitionNode $query
     *
     * @return array
     */
    public static function resolve(FieldDefinitionNode $query)
    {
        $type = directives()->hasResolver($query)
            ? directives()->fieldResolver($query)->handle($query)
            : QueryResolver::resolve($query, self::resolver($query));

        $type['resolve'] = directives()->fieldMiddleware($query)
            ->reduce(function ($resolver, $middleware) use ($query) {
                return $middleware->handle($query, $resolver);
            }, $type['resolve']);

        return $type;
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
