<?php

namespace Nuwave\Lighthouse\Federation\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Federation\BatchedEntityResolver;
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
     *
     * @return list<mixed>
     */
    public function __invoke($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): array
    {
        $results = [];

        /** @var array<string, array<int, array<string, mixed>>> $groupedRepresentations */
        $groupedRepresentations = [];
        foreach ($args['representations'] as $representation) {
            $groupedRepresentations[$representation['__typename']][] = $representation;
        }

        foreach ($groupedRepresentations as $typename => $representations) {
            assert(is_string($typename), 'Never numeric due to GraphQL\Utils::isValidNameError()');

            $resolver = $this->entityResolverProvider->resolver($typename);
            if ($resolver instanceof BatchedEntityResolver) {
                foreach ($resolver($representations) as $result) {
                    $results[] = $result;
                }
            } else {
                foreach ($representations as $representation) {
                    $results[] = $resolver($representation);
                }
            }
        }

        return $results;
    }
}
