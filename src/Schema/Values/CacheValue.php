<?php

namespace Nuwave\Lighthouse\Schema\Values;

use Illuminate\Support\Collection;
use GraphQL\Type\Definition\ResolveInfo;

class CacheValue
{
    /**
     * @var FieldValue
     */
    protected $fieldValue;

    /**
     * @var mixed
     */
    protected $rootValue;

    /**
     * @var array
     */
    protected $args;

    /**
     * @var mixed
     */
    protected $context;

    /**
     * @var ResolveInfo
     */
    protected $resolveInfo;

    /**
     * @var mixed
     */
    protected $fieldKey;

    /**
     * @var bool
     */
    protected $privateCache;

    /**
     * Create instance of cache value.
     *
     * @param array $arguments
     */
    public function __construct(array $arguments = [])
    {
        $this->fieldValue = array_get($arguments, 'field_value');
        $this->rootValue = array_get($arguments, 'root');
        $this->args = array_get($arguments, 'args');
        $this->context = array_get($arguments, 'context');
        $this->resolveInfo = array_get($arguments, 'resolve_info');
        $this->privateCache = array_get($arguments, 'private_cache');

        $this->setFieldKey();
    }

    /**
     * Resolve key from root value.
     *
     * @return mixed
     */
    public function getKey()
    {
        $argKeys = $this->argKeys();

        return $this->implode([
            $this->privateCache ? 'auth' : null,
            $this->privateCache ? auth()->user()->getKey() : null,
            strtolower($this->resolveInfo->parentType->name),
            $this->fieldKey,
            strtolower($this->resolveInfo->fieldName),
            $argKeys->isNotEmpty() ? $argKeys->implode(':') : null,
        ]);
    }

    /**
     * Get cache tags.
     *
     * @todo Check to see if tags are available on the
     * cache store (or add to config) and use tags to
     * flush cache w/out args.
     *
     * @return array
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
     * @return Collection
     */
    protected function argKeys(): Collection
    {
        $args = $this->args;

        ksort($args);

        return collect($args)->map(function ($value, $key) {
            $keyValue = is_array($value)
                ? json_encode($value, true)
                : $value;

            return "{$key}:{$keyValue}";
        });
    }

    /**
     * Set the field key.
     */
    protected function setFieldKey()
    {
        if (! $this->fieldValue || ! $this->rootValue) {
            return null;
        }

        $cacheFieldKey = $this->fieldValue
            ->getParent()
            ->getCacheKey();

        if ($cacheFieldKey) {
            $this->fieldKey = data_get($this->rootValue, $cacheFieldKey);
        }
    }

    /**
     * Implode value to create string.
     *
     * @param array $items
     *
     * @return string
     */
    protected function implode(array $items): string
    {
        return collect($items)
            ->filter()
            ->values()
            ->implode(':');
    }
}
