<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CacheValue
{
    /**
     * @var mixed|null The root that was passed to the query.
     */
    protected $root;

    /**
     * The args that were passed to the query.
     *
     * @var array<string, mixed>
     */
    protected $args;

    /**
     * The context that was passed to the query.
     *
     * @var \Nuwave\Lighthouse\Support\Contracts\GraphQLContext
     */
    protected $context;

    /**
     * The ResolveInfo that was passed to the query.
     *
     * @var \GraphQL\Type\Definition\ResolveInfo
     */
    protected $resolveInfo;

    /**
     * @var \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    protected $fieldValue;

    /**
     * @var bool
     */
    protected $isPrivate;

    /**
     * @var mixed The key to use for caching this field.
     */
    protected $fieldKey;

    /**
     * @param  mixed|null  $root The root that was passed to the query.
     * @param  array<string, mixed>  $args
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     */
    public function __construct(
        $root,
        array $args,
        GraphQLContext $context,
        ResolveInfo $resolveInfo,
        FieldValue $fieldValue,
        bool $isPrivate
    ) {
        $this->root = $root;
        $this->args = $args;
        $this->context = $context;
        $this->resolveInfo = $resolveInfo;
        $this->fieldValue = $fieldValue;
        $this->isPrivate = $isPrivate;

        $this->fieldKey = $this->fieldKey();
    }

    /**
     * Resolve key from root value.
     */
    public function getKey(): string
    {
        $argKeys = $this->argKeys();
        $user = app('auth')->user();

        return $this->implode([
            $this->isPrivate && $user
                ? 'auth'
                : null,
            $this->isPrivate && $user
                ? $user->getKey()
                : null,
            strtolower($this->resolveInfo->parentType->name),
            $this->fieldKey,
            strtolower($this->resolveInfo->fieldName),
            $argKeys->isNotEmpty()
                ? $argKeys->implode(':')
                : null,
        ]);
    }

    /**
     * Get cache tags.
     *
     * @return array<string>
     */
    public function getTags(): array
    {
        $typeTag = $this->implode([
            'graphql',
            strtolower($this->fieldValue->getParentName()),
            $this->fieldKey,
        ]);

        $fieldTag = $this->implode([
            'graphql',
            strtolower($this->fieldValue->getParentName()),
            $this->fieldKey,
            $this->resolveInfo->fieldName,
        ]);

        return [$typeTag, $fieldTag];
    }

    /**
     * Convert input arguments to keys.
     *
     * @return \Illuminate\Support\Collection<string>
     */
    protected function argKeys(): Collection
    {
        // TODO use ->sortKeys() once we drop support for Laravel 5.5
        $args = $this->args;
        ksort($args);

        return (new Collection($args))
            ->map(function ($value, $key): string {
                $keyValue = is_array($value)
                    ? json_encode($value)
                    : $value;

                return "{$key}:{$keyValue}";
            });
    }

    /**
     * Get the field key.
     *
     * @return mixed|void
     */
    protected function fieldKey()
    {
        if ($this->root === null) {
            return;
        }

        $cacheFieldKey = $this->fieldValue
            ->getParent()
            ->getCacheKey();

        if ($cacheFieldKey) {
            return data_get($this->root, $cacheFieldKey);
        }
    }

    /**
     * @param  array<mixed|null> $items
     */
    protected function implode(array $items): string
    {
        return (new Collection($items))
            ->filter()
            ->values()
            ->implode(':');
    }
}
