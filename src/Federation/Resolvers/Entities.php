<?php

namespace Nuwave\Lighthouse\Federation\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Federation\EntityResolverProvider;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Resolver for the _entities field.
 *
 * @see https://www.apollographql.com/docs/federation/federation-spec/#resolve-requests-for-entities
 */
class Entities
{
    /**
     * @var \Nuwave\Lighthouse\Federation\EntityResolverProvider
     */
    protected $entityResolverProvider;

    public function __construct(EntityResolverProvider $entityResolverProvider)
    {
        $this->entityResolverProvider = $entityResolverProvider;
    }

    /**
     * @param  array{representations: array<int, mixed>}  $args
     * @return list<mixed>
     */
    public function __invoke($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): array
    {
        $results = [];

        foreach ($args['representations'] as $representation) {
            $typename = $representation['__typename'];
            $resolver = $this->entityResolverProvider->resolver($typename);

            $results [] = $resolver($representation);
        }

        return $results;
    }
}
