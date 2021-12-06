<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Resolved;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Values\CacheValue;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CacheDirective extends BaseDirective implements FieldMiddleware
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
        $this->setCacheKeyOnParent(
            $fieldValue->getParent()
        );

        // Ensure we run this after all other field middleware
        $fieldValue = $next($fieldValue);

        $shouldUseTags = $this->shouldUseTags();
        $resolver = $fieldValue->getResolver();
        $maxAge = $this->directiveArgValue('maxAge');
        $isPrivate = $this->directiveArgValue('private', false);

        $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($fieldValue, $shouldUseTags, $resolver, $maxAge, $isPrivate) {
                $cacheValue = new CacheValue(
                    $root,
                    $args,
                    $context,
                    $resolveInfo,
                    $fieldValue,
                    $isPrivate
                );

                $cacheKey = $cacheValue->getKey();

                /** @var \Illuminate\Cache\TaggedCache|\Illuminate\Contracts\Cache\Repository $cache */
                $cache = $shouldUseTags
                    ? $this->cacheRepository->tags($cacheValue->getTags())
                    : $this->cacheRepository;

                // We found a matching value in the cache, so we can just return early
                // without actually running the query
                $value = $cache->get($cacheKey);
                if ($value !== null) {
                    return $value;
                }
                // In Laravel cache, null is considered as "non-existant" value. As mentioned in laravel documentation,
                // https://laravel.com/docs/8.x/cache#checking-for-item-existence
                // > The `has` method [...] will also return false if the item exists but its value is null.
                //
                // If caching `null` value becomes something worthwhile, one possible way to achieve it is to
                // encapsulate the `$result` at writing time :
                //
                //    $storeInCache = static function ($result) use ($cacheKey, $maxAge, $cache): void {
                //        $value = ['rawValue' => $result];
                //        $maxAge
                //        ? $cache->put($cacheKey, $value, Carbon::now()->addSeconds($maxAge))
                //        : $cache->forever($cacheKey, $value);
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
            });

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

    /**
     * Set node's cache key.
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    protected function setCacheKeyOnParent(TypeValue $typeValue): void
    {
        if (
            // The cache key was already set, so we do not have to look again
            $typeValue->getCacheKey()
            // The Query type is exempt from requiring a cache key
            || $typeValue->getTypeDefinitionName() === RootType::QUERY
        ) {
            return;
        }

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $typeDefinition */
        $typeDefinition = $typeValue->getTypeDefinition();

        /** @var iterable<\GraphQL\Language\AST\FieldDefinitionNode> $fieldDefinitions */
        $fieldDefinitions = $typeDefinition->fields;

        // First priority: Look for a field with the @cacheKey directive
        foreach ($fieldDefinitions as $field) {
            if (ASTHelper::hasDirective($field, CacheKeyDirective::NAME)) {
                $typeValue->setCacheKey($field->name->value);

                return;
            }
        }

        // Second priority: Look for a Non-Null field with the ID type
        foreach ($fieldDefinitions as $field) {
            if (
                $field->type instanceof NonNullTypeNode
                && $field->type->type instanceof NamedTypeNode
                && $field->type->type->name->value === 'ID'
            ) {
                $typeValue->setCacheKey($field->name->value);

                return;
            }
        }

        throw new DefinitionException(
            "No @cacheKey or ID field defined on {$typeValue->getTypeDefinitionName()}"
        );
    }
}
