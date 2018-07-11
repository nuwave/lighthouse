<?php

namespace Nuwave\Lighthouse\Schema\Values;

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
     * @param FieldValue  $fieldValue
     * @param mixed       $rootValue
     * @param array       $args
     * @param mixed       $context
     * @param ResolveInfo $resolveInfo
     */
    public function __construct(
        $fieldValue,
        $rootValue,
        $args,
        $context,
        $resolveInfo
    ) {
        $this->fieldValue = $fieldValue;
        $this->rootValue = $rootValue;
        $this->args = $args;
        $this->context = $context;
        $this->resolveInfo = $resolveInfo;

        $this->setFieldKey();
    }

    /**
     * Resolve key from root value.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getKey()
    {
        $argKeys = $this->argKeys();

        return sprintf(
            '%s:%s:%s%s',
            strtolower($this->resolveInfo->parentType->name),
            $this->fieldKey,
            strtolower($this->resolveInfo->fieldName),
            $argKeys->isNotEmpty() ? ':'.$argKeys->implode(':') : null
        );
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
    public function getTags()
    {
        $fieldTag = collect([
            strtolower($this->fieldValue->getNodeName()),
            $this->resolveInfo->fieldName,
            $this->fieldKey,
        ])->filter()->values()->implode(':');

        return ['graphql', $fieldTag];
    }

    /**
     * Convert input arguments to keys.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function argKeys()
    {
        return collect($this->args)
            ->sortKeys()
            ->map(function ($value, $key) {
                $keyValue = is_array($value) ? json_encode($value, true) : $value;

                return "{$key}:{$keyValue}";
            });
    }

    /**
     * Set the field key.
     */
    protected function setFieldKey()
    {
        $cacheFieldKey = $this->fieldValue->getNode()->getCacheKey();

        if ($cacheFieldKey) {
            $this->fieldKey = data_get($this->rootValue, $cacheFieldKey);
        }
    }
}
