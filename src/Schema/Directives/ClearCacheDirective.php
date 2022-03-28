<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Schema\Values\CacheKeyAndTags;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ClearCacheDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cacheRepository;

    public function __construct(CacheRepository $cacheRepository)
    {
        $this->cacheRepository = $cacheRepository;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Clear a resolver cache by tags.
"""
directive @clearCache(
  """
  Name of the parent type of the field to clear.
  """
  type: String!

  """
  Source of the parent ID to clear.
  """
  idSource: ClearCacheIdSource

  """
  Name of the field to clear.
  """
  field: String
) on FIELD_DEFINITION

"""
Options for the `id` argument on `@clearCache`.

Exactly one of the fields must be given.
"""
input ClearCacheIdSource {
  """
  Path of an argument the client passes to the field `@clearCache` is applied to.
  """
  argument: String

  """
  Path of a field in the result returned from the field `@clearCache` is applied to.
  """
  field: String
}
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $fieldValue->resultHandler(
            function ($result, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
                $type = $this->directiveArgValue('type');
                $idSource = $this->directiveArgValue('idSource');
                $field = $this->directiveArgValue('field');

                if (isset($idSource['argument'])) {
                    $idOrIds = Arr::get($args, $idSource['argument']);
                } elseif (isset($idSource['field'])) {
                    $idOrIds = data_get($result, $idSource['field']);
                } else {
                    $idOrIds = [null];
                }

                foreach ((array) $idOrIds as $id) {
                    $tag = is_string($field)
                        ? CacheKeyAndTags::fieldTag($type, $id, $field)
                        : CacheKeyAndTags::parentTag($type, $id);

                    $this->cacheRepository->tags([$tag])->flush();
                }

                return $result;
            }
        );

        return $next($fieldValue);
    }
}
