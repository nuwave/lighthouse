<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Carbon\Carbon;
use GraphQL\Deferred;
use Illuminate\Support\Facades\Cache;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\Values\CacheValue;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
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
     * @param FieldValue $value
     * @param \Closure   $next
     *
     * @throws DirectiveException
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next): FieldValue
    {
        $this->setNodeKey(
            $value->getParent()
        );

        $value = $next($value);
        $resolver = $value->getResolver();
        $maxAge = $this->directiveArgValue('maxAge');
        $privateCache = $this->directiveArgValue('private', false);

        return $value->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $info) use ($value, $resolver, $maxAge, $privateCache) {
            /** @var Cache $cache */
            $cache = app('cache');
            $cacheValue = new CacheValue([
                'field_value' => $value,
                'root' => $root,
                'args' => $args,
                'context' => $context,
                'resolve_info' => $info,
                'private_cache' => $privateCache,
            ]);

            $useTags = $this->useTags();
            $cacheExp = $maxAge
                ? Carbon::now()->addSeconds($maxAge)
                : null;
            $cacheKey = $cacheValue->getKey();
            $cacheTags = $cacheValue->getTags();
            $cacheHas = $useTags
                ? $cache->tags($cacheTags)->has($cacheKey)
                : $cache->has($cacheKey);

            if ($cacheHas) {
                return $useTags
                    ? $cache->tags($cacheTags)->get($cacheKey)
                    : $cache->get($cacheKey);
            }

            $resolvedValue = $resolver($root, $args, $context, $info);

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
     * @param Cache       $cache
     * @param string      $key
     * @param mixed       $value
     * @param Carbon|null $expiration
     * @param array       $tags
     *
     * @return void
     */
    protected function store(Cache $cache, string $key, $value, ?Carbon $expiration, array $tags): void
    {
        $supportsTags = $this->useTags();

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
     * @return bool
     */
    protected function useTags(): bool
    {
        return config('lighthouse.cache.tags', false)
            && method_exists(app('cache')->store(), 'tags');
    }

    /**
     * Set node's cache key.
     *
     * @param NodeValue $nodeValue
     *
     * @throws DirectiveException
     *
     * @return void
     */
    protected function setNodeKey(NodeValue $nodeValue): void
    {
        if ($nodeValue->getCacheKey()) {
            return;
        }

        $fields = data_get($nodeValue->getTypeDefinition(), 'fields', []);
        $nodeKey = collect($fields)->reduce(function ($key, $field): ?string {
            if ($key) {
                return $key;
            }

            $hasCacheKey = collect(data_get($field, 'directives', []))
                ->contains(function (DirectiveNode $directive): bool {
                    return 'cacheKey' === $directive->name->value;
                });

            return $hasCacheKey ? data_get($field, 'name.value') : $key;
        });

        if (! $nodeKey) {
            $nodeKey = collect($fields)->reduce(function ($key, $field): ?string {
                if ($key) {
                    return $key;
                }

                $type = $field->type;
                while (! is_null(data_get($type, 'type'))) {
                    $type = data_get($type, 'type');
                }

                return 'ID' === data_get($type, 'name.value')
                    ? data_get($field, 'name.value')
                    : $key;
            });
        }

        if (! $nodeKey && 'Query' !== $nodeValue->getTypeDefinitionName()) {
            $message = sprintf(
                'No @cacheKey or ID field defined on %s',
                $nodeValue->getTypeDefinitionName()
            );

            throw new DirectiveException($message);
        }

        $nodeValue->setCacheKey($nodeKey);
    }
}
