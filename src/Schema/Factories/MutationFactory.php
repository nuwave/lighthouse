<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\Resolvers\MutationResolver;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

class MutationFactory
{
    /**
     * Convert field definition to mutation.
     *
     * @param FieldDefinitionNode $mutation
     * @param Node                $node
     *
     * @return array
     */
    public static function resolve(FieldDefinitionNode $mutation, Node $node)
    {
        $value = new FieldValue($node, $mutation, '');

        if (directives()->hasResolver($mutation)) {
            directives()->fieldResolver($mutation)->handle($value);
        } else {
            $value->setResolver(
                MutationResolver::resolve($mutation, self::resolver($mutation))
            );
        }

        directives()->fieldMiddleware($mutation)
            ->reduce(function ($value, $middleware) {
                return $middleware->handle($value);
            }, $value);

        return [
            'type' => $value->getType(),
            'description' => $value->getDescription(),
            'resolve' => $value->getResolver(),
        ];
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
