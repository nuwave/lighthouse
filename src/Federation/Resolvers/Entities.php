<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Resolvers;

use Nuwave\Lighthouse\Execution\ResolveInfo;
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
    public function __construct(
        protected EntityResolverProvider $entityResolverProvider,
    ) {}

    /**
     * @param  array{representations: list<mixed>}  $args
     *
     * @return array<mixed>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): array
    {
        $results = [];

        $representations = $args['representations'];
        $representationHashes = array_map('serialize', $representations);

        $assignResultsByHash = static function ($result, string $hash) use ($representationHashes, &$results): void {
            foreach ($representationHashes as $index => $h) {
                if ($hash === $h) {
                    $results[$index] = $result;
                }
            }
        };

        /**
         * Firstly, representations are grouped by typename to allow assigning the correct resolver for each entity.
         * Secondly, they are deduplicated based on their hash to avoid resolving the same entity twice.
         *
         * @var array<string, array<string, array<string, mixed>>> $groupedRepresentations
         */
        $groupedRepresentations = [];
        foreach ($representations as $index => $representation) {
            $typename = $representation['__typename'];
            $hash = $representationHashes[$index];
            $groupedRepresentations[$typename][$hash] = $representation;
        }

        foreach ($groupedRepresentations as $typename => $representations) {
            assert(is_string($typename), 'Never numeric due to GraphQL\Utils::isValidNameError()');

            $resolver = $this->entityResolverProvider->resolver($typename);
            if ($resolver instanceof BatchedEntityResolver) {
                foreach ($resolver($representations) as $hash => $result) {
                    $assignResultsByHash($result, $hash);
                }
            } else {
                foreach ($representations as $hash => $representation) {
                    $result = $resolver($representation);
                    $assignResultsByHash($result, $hash);
                }
            }
        }

        ksort($results);

        return $results;
    }
}
