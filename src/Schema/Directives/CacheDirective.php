<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Language\AST\DirectiveNode;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Factories\ValueFactory;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class CacheDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'cache';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     * @param Closure    $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, Closure $next)
    {
        $this->setNodeKey($value->getNode());

        $value = $next($value);
        $resolver = $value->getResolver();
        $maxAge = $this->directiveArgValue('maxAge');

        return $value->setResolver(function () use ($value, $resolver, $maxAge) {
            $arguments = func_get_args();
            /** @var \Illuminate\Support\Facades\Cache $cache */
            $cache = app('cache');
            /** @var \Nuwave\Lighthouse\Schema\Values\CacheValue $cacheValue */
            $cacheValue = call_user_func_array(
                [app(ValueFactory::class), 'cache'],
                array_merge([$value], $arguments)
            );

            $useTags = $this->useTags();
            $cacheExp = $maxAge ? now()->addSeconds($maxAge) : null;
            $cacheKey = $cacheValue->getKey();
            $cacheTags = $cacheValue->getTags();
            $cacheHas = $useTags ? $cache->tags($cacheTags)->has($cacheKey) : $cache->has($cacheKey);

            if ($cacheHas) {
                return $useTags
                    ? $cache->tags($cacheTags)->get($cacheKey)
                    : $cache->get($cacheKey);
            }

            $value = call_user_func_array($resolver, $arguments);

            ($value instanceof \GraphQL\Deferred)
                ? $value->then(function ($result) use ($cache, $cacheKey, $cacheExp, $cacheTags) {
                    $this->store($cache, $cacheKey, $result, $cacheExp, $cacheTags);
                })
                : $this->store($cache, $cacheKey, $value, $cacheExp, $cacheTags);

            return $value;
        });
    }

    /**
     * Store value in cache.
     *
     * @param \Illuminate\Support\Facades\Cache $cache
     * @param string                            $key
     * @param mixed                             $value
     * @param \Carbon\Carbon|null               $expiration
     * @param array                             $tags
     */
    protected function store($cache, $key, $value, $expiration, $tags)
    {
        $store = $cache->store();
        $supportsTags = $this->useTags();

        if ($expiration) {
            ($supportsTags)
                ? $cache->tags($tags)->put($key, $value, $expiration)
                : $cache->put($key, $value, $expiration);

            return;
        }

        ($supportsTags)
            ? $cache->tags($tags)->forever($key, $value)
            : $cache->forever($key, $value);
    }

    /**
     * Check if tags should be used.
     *
     * @return bool
     */
    protected function useTags()
    {
        return config('lighthouse.cache.tags', false) && method_exists(app('cache')->store(), 'tags');
    }

    /**
     * Set node's cache key.
     *
     * @param NodeValue $nodeValue
     */
    protected function setNodeKey(NodeValue $nodeValue)
    {
        if ($nodeValue->getCacheKey()) {
            return;
        }

        $fields = data_get($nodeValue->getNode(), 'fields', []);
        $nodeKey = collect($fields)->reduce(function ($key, $field) {
            if ($key) {
                return $key;
            }

            $hasCacheKey = collect(data_get($field, 'directives', []))
                ->contains(function (DirectiveNode $directive) {
                    return 'cacheKey' === $directive->name->value;
                });

            return $hasCacheKey ? data_get($field, 'name.value') : $key;
        });

        if (! $nodeKey) {
            $nodeKey = collect($fields)->reduce(function ($key, $field) {
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

        if (! $nodeKey && 'Query' !== $nodeValue->getNodeName()) {
            $message = sprintf(
                'No @cacheKey or ID field defined on %s',
                $nodeValue->getNodeName()
            );

            throw new DirectiveException($message);
        }

        $nodeValue->setCacheKey($nodeKey);
    }
}
