<?php

namespace Nuwave\Lighthouse\Federation\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Exceptions\FederationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

/**
 * Resolver for the _entities field.
 *
 * @see https://www.apollographql.com/docs/federation/federation-spec/#resolve-requests-for-entities
 */
class Entities
{
    /**
     * @param array{representations: array<int, mixed>} $args
     * @return array<int, mixed>
     */
    public function __invoke($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): array
    {
        $results = [];

        foreach ($args['representations'] as $representation) {
            $typename = $representation['__typename'];

            $resolverClass = Utils::namespaceClassname(
                $typename,
                (array) config('lighthouse.federation.entities_resolver_namespace'),
                'class_exists'
            );
            if ($resolverClass === null) {
                throw new FederationException("Could not locate entity resolver for typename {$typename}.");
            }

            $resolver = Utils::constructResolver($resolverClass, '__invoke');

            $results [] = $resolver($representation);
        }

        return $results;
    }
}
