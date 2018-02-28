<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\Resolvers\MutationResolver;

class MutationFactory
{
    /**
     * Convert field definition to mutation.
     *
     * @param FieldDefinitionNode $mutation
     *
     * @return array
     */
    public static function resolve(FieldDefinitionNode $mutation)
    {
        $type = directives()->hasResolver($mutation)
            ? directives()->fieldResolver($mutation)->handle($mutation)
            : MutationResolver::resolve($mutation, self::resolver($mutation));

        $type['resolve'] = directives()->fieldMiddleware($mutation)
            ->reduce(function ($resolver, $middleware) use ($mutation) {
                return $middleware->handle($mutation, $resolver);
            }, $type['resolve']);

        return $type;
    }

    /**
     * Attempt to get resolver for mutation name.
     *
     * @param FieldDefinitionNode $mutation
     *
     * @return \Closure
     */
    public static function resolver(FieldDefinitionNode $mutation)
    {
        $class = config('lighthouse.namespaces.mutations').'\\'.studly_case($mutation->name->value);

        $mutation = app($class);

        return (new \ReflectionClass($mutation))->getMethod('resolve')->getClosure($mutation);
    }
}
