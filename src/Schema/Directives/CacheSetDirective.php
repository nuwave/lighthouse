<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Execution\Resolved;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CacheSetDirective extends BaseDirective implements FieldMiddleware
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
directive @cacheSet(
  """
  Set the key cache
  """
  key: String
  """
  Set the duration it takes for the cache to expire in seconds.
  If not given, the result will be stored forever.
  """
  maxAge: Int
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {

        // Ensure we run this after all other field middleware
        $fieldValue = $next($fieldValue);

        $resolver = $fieldValue->getResolver();
        $key = $this->directiveArgValue('key') ?? $this->nodeName();
        $maxAge = $this->directiveArgValue('maxAge');

        $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver, $key, $maxAge) {
                $cache = $this->cacheRepository;

                // We found a matching value in the cache, so we can just return early
                // without actually running the query
                if ($value = $cache->get($key)) {
                    return $value;
                }

                $resolved = $resolver($root, $args, $context, $resolveInfo);

                $storeInCache = $maxAge
                    ? static function ($result) use ($key, $maxAge, $cache): void {
                        $cache->put($key, $result, Carbon::now()->addSeconds($maxAge));
                    }
                : static function ($result) use ($key, $cache): void {
                    $cache->forever($key, $result);
                };

                Resolved::handle($resolved, $storeInCache);

                return $resolved;
            });

        return $fieldValue;
    }
}
