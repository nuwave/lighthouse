<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class ClearCacheDirective extends BaseDirective implements FieldMiddleware
{
    public function __construct(
        protected CacheRepository $cacheRepository,
        protected CacheKeyAndTags $cacheKeyAndTags,
    ) {}

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
) repeatable on FIELD_DEFINITION

"""
Options for the `idSource` argument of `@clearCache`.

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

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->resultHandler(function ($result, array $args): mixed {
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
                    ? $this->cacheKeyAndTags->fieldTag($type, $id, $field)
                    : $this->cacheKeyAndTags->parentTag($type, $id);

                $this->cacheRepository->tags([$tag])
                    ->flush();
            }

            return $result;
        });
    }
}
