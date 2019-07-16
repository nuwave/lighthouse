<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Carbon\Carbon;
use GraphQL\Deferred;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Schema\Values\CacheValue;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class CacheDirective extends BaseDirective implements FieldMiddleware
{
    /** @var \Illuminate\Cache\CacheManager */
    protected $cacheManager;

    /**
     * @param  \Illuminate\Cache\CacheManager  $cacheManager
     * @return void
     */
    public function __construct(CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

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
        $this->setCacheKeyOnParent(
            $fieldValue->getParent()
        );

        // Ensure we run this after all other field middleware
        $fieldValue = $next($fieldValue);

        $resolver = $fieldValue->getResolver();

        $maxAge = $this->directiveArgValue('maxAge');
        $isPrivate = $this->directiveArgValue('private', false);

        return $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($fieldValue, $resolver, $maxAge, $isPrivate) {
            $cacheValue = new CacheValue([
                'field_value' => $fieldValue,
                'root' => $root,
                'args' => $args,
                'context' => $context,
                'resolve_info' => $resolveInfo,
                'is_private' => $isPrivate,
            ]);

            $cacheKey = $cacheValue->getKey();

            /** @var \Illuminate\Cache\Repository|\Illuminate\Cache\TaggedCache $repository */
            $cache = $this->shouldUseTags()
                ? $this->cacheManager->tags($cacheValue->getTags())
                : $this->cacheManager;

            $cacheHasKey = $cache->has($cacheKey);

            // We found a matching value in the cache, so we can just return early
            // without actually running the query
            if ($cacheHasKey) {
                return $cache->get($cacheKey);
            }

            $resolvedValue = $resolver($root, $args, $context, $resolveInfo);

            $storeInCache = $maxAge
                ? function ($value) use ($cacheKey, $maxAge, $cache) {
                    $cache->put($cacheKey, $value, Carbon::now()->addSeconds($maxAge));
                }
            : function ($value) use ($cacheKey, $cache) {
                $cache->forever($cacheKey, $value);
            };

            ($resolvedValue instanceof Deferred)
                ? $resolvedValue->then(function ($result) use ($storeInCache): void {
                    $storeInCache($result);
                })
                : $storeInCache($resolvedValue);

            return $resolvedValue;
        });
    }

    /**
     * Check if tags should be used and are available.
     *
     * @return bool
     */
    protected function shouldUseTags(): bool
    {
        return config('lighthouse.cache.tags', false)
            && method_exists($this->cacheManager->store(), 'tags');
    }

    /**
     * Set node's cache key.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\TypeValue  $typeValue
     * @return void
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    protected function setCacheKeyOnParent(TypeValue $typeValue): void
    {
        if (
            // The cache key was already set, so we do not have to look again
            $typeValue->getCacheKey()
            // The Query type is exempt from requiring a cache key
            || $typeValue->getTypeDefinitionName() === 'Query'
        ) {
            return;
        }

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $typeDefinition */
        $typeDefinition = $typeValue->getTypeDefinition();

        // First priority: Look for a field with the @cacheKey directive
        /** @var FieldDefinitionNode $field */
        foreach ($typeDefinition->fields as $field) {
            $hasCacheKey = (new Collection($field->directives))
                ->contains(function (DirectiveNode $directive): bool {
                    return $directive->name->value === 'cacheKey';
                });

            if ($hasCacheKey) {
                $typeValue->setCacheKey(
                    $field->name->value
                );

                return;
            }
        }

        // Second priority: Look for a Non-Null field with the ID type
        /** @var FieldDefinitionNode $field */
        foreach ($typeDefinition->fields as $field) {
            if (
                $field->type instanceof NonNullTypeNode
                && $field->type->type->name->value === 'ID'
            ) {
                $typeValue->setCacheKey(
                    $field->name->value
                );

                return;
            }
        }

        throw new DirectiveException(
            "No @cacheKey or ID field defined on {$typeValue->getTypeDefinitionName()}"
        );
    }
}
