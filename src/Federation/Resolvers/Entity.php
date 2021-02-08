<?php

namespace Nuwave\Lighthouse\Federation\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

class Entity
{
    /**
     * @param array{representations: array<int, mixed>} $args
     * @return array<int, mixed>
     */
    public function resolve($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): array
    {
        $results = [];

        foreach($args['representations'] as $representation) {
            $typename = $representation['__typename'];
            $resolverClass = Utils::namespaceClassname($typename, config('lighthouse.federation.namespace'), 'class_exists');
            $resolver = Utils::constructResolver($resolverClass, '__invoke');

            $results []= $resolver($representation);
        }

        return $results;
    }
}
