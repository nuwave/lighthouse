<?php

namespace Nuwave\Lighthouse\Schema\Values;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class CacheValue
{
    /**
     * @var \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    protected $fieldValue;

    protected $rootValue;

    /**
     * @var array
     */
    protected $args;

    protected $context;

    /**
     * @var \GraphQL\Type\Definition\ResolveInfo
     */
    protected $resolveInfo;

    protected $fieldKey;

    /**
     * @var bool
     */
    protected $isPrivate;

    public function __construct(array $arguments = [])
    {
        $this->fieldValue = Arr::get($arguments, 'field_value');
        $this->rootValue = Arr::get($arguments, 'root');
        $this->args = Arr::get($arguments, 'args');
        $this->context = Arr::get($arguments, 'context');
        $this->resolveInfo = Arr::get($arguments, 'resolve_info');
        $this->isPrivate = Arr::get($arguments, 'is_private');

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
     */
    protected function argKeys(): Collection
    {
        // TODO use ->sortKeys() once we drop support for Laravel 5.5
        $args = $this->args;
        ksort($args);

        return (new Collection($args))
            ->map(function ($value, $key): string {
                $keyValue = is_array($value)
                    ? json_encode($value, true)
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
        if (! $this->fieldValue || ! $this->rootValue) {
            return;
        }

        $cacheFieldKey = $this->fieldValue
            ->getParent()
            ->getCacheKey();

        if ($cacheFieldKey) {
            return data_get($this->rootValue, $cacheFieldKey);
        }
    }

    /**
     * Implode value to create string.
     */
    protected function implode(array $items): string
    {
        return (new Collection($items))
            ->filter()
            ->values()
            ->implode(':');
    }
}
