<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Cache;

use GraphQL\Deferred;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Execution\Resolved;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CacheDirective extends BaseDirective implements FieldMiddleware
{
    public function __construct(
        protected CacheRepository $cacheRepository,
        protected CacheKeyAndTags $cacheKeyAndTags,
    ) {}

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Cache the result of a resolver.

Place this after other field middleware to ensure it caches the correct transformed value.
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

    public function handleField(FieldValue $fieldValue): void
    {
        $rootCacheKey = $fieldValue->getParent()->cacheKey();
        $shouldUseTags = $this->shouldUseTags();
        $maxAge = $this->directiveArgValue('maxAge');
        $isPrivate = $this->directiveArgValue('private', false);

        $fieldValue->wrapResolver(fn (callable $resolver): \Closure => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($rootCacheKey, $shouldUseTags, $resolver, $maxAge, $isPrivate) {
            $parentName = $resolveInfo->parentType->name;
            $rootID = $root !== null && $rootCacheKey !== null
                ? data_get($root, $rootCacheKey)
                : null;
            $fieldName = $resolveInfo->fieldName;
            $path = $resolveInfo->path;

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
                $args,
                $path,
            );

            // We found a matching value in the cache, so we can just return early without actually running the query.
            $value = $cache->get($cacheKey);
            if ($value !== null) {
                // Deferring the result will allow nested deferred resolves to be bundled together, see https://github.com/nuwave/lighthouse/pull/2270#discussion_r1072414584.
                return new Deferred(static fn () => $value);
            }

            // In Laravel cache, null is considered a non-existent value, see https://laravel.com/docs/9.x/cache#checking-for-item-existence:
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
            // less collision-probable key instead of `rawValue` (e.g. "lighthouse:rawValue").

            $resolved = $resolver($root, $args, $context, $resolveInfo);

            $storeInCache = $maxAge
                ? static fn ($result): bool => $cache->put($cacheKey, $result, Carbon::now()->addSeconds($maxAge))
                : static fn ($result): bool => $cache->forever($cacheKey, $result);

            Resolved::handle($resolved, $storeInCache);

            return $resolved;
        });
    }

    /** Check if tags should be used and are available. */
    protected function shouldUseTags(): bool
    {
        return config('lighthouse.cache_directive_tags', false)
            && method_exists($this->cacheRepository->getStore(), 'tags');
    }
}
