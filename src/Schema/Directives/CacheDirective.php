<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Carbon\Carbon;
use GraphQL\Deferred;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\Values\CacheValue;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class CacheDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'cache';
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $this->setNodeKey(
            $fieldValue->getParent()
        );

        $fieldValue = $next($fieldValue);
        $resolver = $fieldValue->getResolver();
        $maxAge = $this->directiveArgValue('maxAge');
        $privateCache = $this->directiveArgValue('private', false);

        return $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($fieldValue, $resolver, $maxAge, $privateCache) {
            $cacheValue = new CacheValue([
                'field_value' => $fieldValue,
                'root' => $root,
                'args' => $args,
                'context' => $context,
                'resolve_info' => $resolveInfo,
                'private_cache' => $privateCache,
            ]);

            $cacheKey = $cacheValue->getKey();
            $cacheTags = $cacheValue->getTags();

            /** @var \Illuminate\Cache\CacheManager $cache */
            $cache = app('cache');
            $useTags = $this->useTags($cache);

            $cacheHas = $useTags
                ? $cache->tags($cacheTags)->has($cacheKey)
                : $cache->has($cacheKey);

            if ($cacheHas) {
                return $useTags
                    ? $cache->tags($cacheTags)->get($cacheKey)
                    : $cache->get($cacheKey);
            }

            $resolvedValue = $resolver($root, $args, $context, $resolveInfo);

            $cacheExp = $maxAge
                ? Carbon::now()->addSeconds($maxAge)
                : null;

            ($resolvedValue instanceof Deferred)
                ? $resolvedValue->then(function ($result) use ($cache, $cacheKey, $cacheExp, $cacheTags): void {
                    $this->store($cache, $cacheKey, $result, $cacheExp, $cacheTags);
                })
                : $this->store($cache, $cacheKey, $resolvedValue, $cacheExp, $cacheTags);

            return $resolvedValue;
        });
    }

    /**
     * Store value in cache.
     *
     * @param  \Illuminate\Cache\CacheManager  $cache
     * @param  string  $key
     * @param  mixed  $value
     * @param  \Carbon\Carbon|null  $expiration
     * @param  mixed[]  $tags
     * @return void
     */
    protected function store(CacheManager $cache, string $key, $value, ?Carbon $expiration, array $tags): void
    {
        $supportsTags = $this->useTags($cache);

        if ($expiration) {
            $supportsTags
                ? $cache->tags($tags)->put($key, $value, $expiration)
                : $cache->put($key, $value, $expiration);

            return;
        }

        $supportsTags
            ? $cache->tags($tags)->forever($key, $value)
            : $cache->forever($key, $value);
    }

    /**
     * Check if tags should be used.
     *
     * @param  \Illuminate\Cache\CacheManager  $cache
     * @return bool
     */
    protected function useTags(CacheManager $cache): bool
    {
        return config('lighthouse.cache.tags', false)
            && method_exists($cache->store(), 'tags');
    }

    /**
     * Set node's cache key.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\NodeValue  $nodeValue
     * @return void
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    protected function setNodeKey(NodeValue $nodeValue): void
    {
        if ($nodeValue->getCacheKey()) {
            return;
        }

        $fields = data_get($nodeValue->getTypeDefinition(), 'fields', []);
        $nodeKey = (new Collection($fields))->reduce(function (?string $key, FieldDefinitionNode $field): ?string {
            if ($key) {
                return $key;
            }

            $hasCacheKey = (new Collection($field->directives))
                ->contains(function (DirectiveNode $directive): bool {
                    return $directive->name->value === 'cacheKey';
                });

            return $hasCacheKey
                ? $field->name->value
                : $key;
        });

        if (! $nodeKey) {
            $nodeKey = (new Collection($fields))->reduce(function (?string $key, FieldDefinitionNode $field): ?string {
                if ($key) {
                    return $key;
                }

                $typeName = ASTHelper::getUnderlyingTypeName($field);

                return $typeName === 'ID'
                    ? $field->name->value
                    : $key;
            });
        }

        if (! $nodeKey && $nodeValue->getTypeDefinitionName() !== 'Query') {
            throw new DirectiveException(
                "No @cacheKey or ID field defined on {$nodeValue->getTypeDefinitionName()}"
            );
        }

        $nodeValue->setCacheKey($nodeKey);
    }
}
