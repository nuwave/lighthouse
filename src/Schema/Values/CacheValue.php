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
     * Resolve key from root value.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getKey()
    {
        $cacheFieldKey = $this->fieldValue->getNode()->getCacheKey();
        $key = $cacheFieldKey ? data_get($this->rootValue, $cacheFieldKey) : null;
        $argKeys = $this->argKeys();

        return sprintf(
            '%s:%s:%s%s',
            strtolower($this->fieldValue->getNodeName()),
            $key,
            strtolower($this->fieldValue->getFieldName()),
            $argKeys->isNotEmpty() ? ':'.$argKeys->implode(':') : null
        );
    }
}
