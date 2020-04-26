<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Carbon\Carbon;
use Closure;
use GraphQL\Deferred;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Cache\CacheManager;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\Values\CacheValue;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CacheDirective extends BaseDirective implements FieldMiddleware, DefinedDirective
{
    /**
     * @var \Illuminate\Cache\CacheManager|\Illuminate\Cache\Repository
     */
    protected $cacheManager;

    public function __construct(CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
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
SDL;
    }

    /**
     * Resolve the field directive.
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

            /** @var \Illuminate\Cache\Repository|\Illuminate\Cache\TaggedCache $cache */
            $cache = $this->shouldUseTags()
                ? $this->cacheManager->tags($cacheValue->getTags())
                : $this->cacheManager;

            // We found a matching value in the cache, so we can just return early
            // without actually running the query
            if ($value = $cache->get($cacheKey)) {
                return $value;
            }

            $resolvedValue = $resolver($root, $args, $context, $resolveInfo);

            $storeInCache = $maxAge
                ? function ($value) use ($cacheKey, $maxAge, $cache) {
                    $cache->put($cacheKey, $value, Carbon::now()->addSeconds($maxAge));
                }
            : function ($value) use ($cacheKey, $cache) {
                $cache->forever($cacheKey, $value);
            };

            $resolvedValue instanceof Deferred
                ? $resolvedValue->then(function ($result) use ($storeInCache): void {
                    $storeInCache($result);
                })
                : $storeInCache($resolvedValue);

            return $resolvedValue;
        });
    }

    /**
     * Check if tags should be used and are available.
     */
    protected function shouldUseTags(): bool
    {
        return config('lighthouse.cache.tags', false)
            && method_exists($this->cacheManager->store(), 'tags');
    }

    /**
     * Set node's cache key.
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
        /** @var \GraphQL\Language\AST\FieldDefinitionNode $field */
        foreach ($typeDefinition->fields as $field) {
            if (ASTHelper::hasDirective($field, 'cacheKey')) {
                $typeValue->setCacheKey($field->name->value);

                return;
            }
        }

        // Second priority: Look for a Non-Null field with the ID type
        /** @var \GraphQL\Language\AST\FieldDefinitionNode $field */
        foreach ($typeDefinition->fields as $field) {
            if (
                // @phpstan-ignore-next-line TODO remove once graphql-php is accurate
                $field->type instanceof NonNullTypeNode
                // @phpstan-ignore-next-line TODO remove once graphql-php is accurate
                && $field->type->type instanceof NamedTypeNode
                && $field->type->type->name->value === 'ID'
            ) {
                $typeValue->setCacheKey($field->name->value);

                return;
            }
        }

        throw new DirectiveException(
            "No @cacheKey or ID field defined on {$typeValue->getTypeDefinitionName()}"
        );
    }
}
