<?php

namespace Nuwave\Lighthouse\Cache;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Execution\Resolved;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CacheDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cacheRepository;

    /**
     * @var \Nuwave\Lighthouse\Cache\CacheKeyAndTags
     */
    protected $cacheKeyAndTags;

    public function __construct(CacheRepository $cacheRepository, CacheKeyAndTags $cacheKeyAndTags)
    {
        $this->cacheRepository = $cacheRepository;
        $this->cacheKeyAndTags = $cacheKeyAndTags;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Cache the result of a resolver.
"""
directive @cache(
  """
  Set the duration it takes for the cache to expire in seconds.
  If not given, the result will be stored forever.
  """
  maxAge: Int

  """
  Limit access to cached data to the currently authenticated user.
  When the field is accessible by guest users, this will not have
  any effect, they will access a shared cache.
  """
  private: Boolean = false
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        // Ensure we run this after other field middleware
        $fieldValue = $next($fieldValue);

        $rootCacheKey = $fieldValue->getParent()->cacheKey();
        $shouldUseTags = $this->shouldUseTags();
        $resolver = $fieldValue->getResolver();
        $maxAge = $this->directiveArgValue('maxAge');
        $isPrivate = $this->directiveArgValue('private', false);

        $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($rootCacheKey, $shouldUseTags, $resolver, $maxAge, $isPrivate) {
                $parentName = $resolveInfo->parentType->name;
                $rootID = null !== $root && null !== $rootCacheKey
                    ? data_get($root, $rootCacheKey)
                    : null;
                $fieldName = $resolveInfo->fieldName;

                /** @var \Illuminate\Cache\TaggedCache|\Illuminate\Contracts\Cache\Repository $cache */
                $cache = $shouldUseTags
                    ? $this->cacheRepository->tags([
                        $this->cacheKeyAndTags->parentTag($parentName, $rootID),
                        $this->cacheKeyAndTags->fieldTag($parentName, $rootID, $fieldName),
                    ])
                    : $this->cacheRepository;

                $cacheKey = $this->cacheKeyAndTags->key(
                    $context->user(),
                    $isPrivate,
                    $parentName,
                    $rootID,
                    $fieldName,
                    $args
                );

                // We found a matching value in the cache, so we can just return early
                // without actually running the query
                $value = $cache->get($cacheKey);
                if (null !== $value) {
                    return $value;
                }

                // In Laravel cache, null is considered a non-existent value, see https://laravel.com/docs/8.x/cache#checking-for-item-existence:
                // > The `has` method [...] will also return false if the item exists but its value is null.
                //
                // If caching `null` value becomes something worthwhile, one possible way to achieve it is to
                // encapsulate the `$result` at writing time :
                //
                //    $storeInCache = static function ($result) use ($cacheKey, $maxAge, $cache): void {
                //        $value = ['rawValue' => $result];
                //        $maxAge
                //            ? $cache->put($cacheKey, $value, Carbon::now()->addSeconds($maxAge))
                //            : $cache->forever($cacheKey, $value);
                //    };
                //
                // and restoring original value back at reading :
                //
                //    if (is_array($value) && array_key_exists('rawValue', $value)) { // don't use isset !
                //        return $value['rawValue'];
                //    }
                //
                // Such a change would introduce some potential BC, if for instance cached value was already containing
                // an object with a `rawValue` key prior the implementation change. A possible workaround is to choose a
                // less collision-probable key instead of `rawValue` (eg. "com.lighthouse-php:rawValue" ?)

                $resolved = $resolver($root, $args, $context, $resolveInfo);

                $storeInCache = $maxAge
                    ? static function ($result) use ($cacheKey, $maxAge, $cache): void {
                        $cache->put($cacheKey, $result, Carbon::now()->addSeconds($maxAge));
                    }
                : static function ($result) use ($cacheKey, $cache): void {
                    $cache->forever($cacheKey, $result);
                };

                Resolved::handle($resolved, $storeInCache);

                return $resolved;
            }
        );

        return $fieldValue;
    }

    /**
     * Check if tags should be used and are available.
     */
    protected function shouldUseTags(): bool
    {
        return config('lighthouse.cache.tags', false)
            && method_exists($this->cacheRepository->getStore(), 'tags');
    }
}
