<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class DeleteCacheDirective extends BaseDirective implements FieldMiddleware
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
Cache the result of a resolver.
"""
directive @deleteCache(
  """
  Set the key cache
  """
  key: String!
) on FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * @param FieldValue $fieldValue
     * @param Closure $next
     * @return FieldValue
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        // Ensure we run this after all other field middleware
        $fieldValue = $next($fieldValue);

        $key = $this->directiveArgValue('key');

        // Delete Cache By Key
        $cache = $this->cacheRepository;
        $cache->forget($key);

        return $fieldValue;
    }
}
