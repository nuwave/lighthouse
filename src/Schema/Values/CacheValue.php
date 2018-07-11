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

        return sprintf(
            '%s:%s:%s',
            strtolower($this->fieldValue->getNodeName()),
            $key,
            strtolower($this->fieldValue->getFieldName())
        );
    }
}
