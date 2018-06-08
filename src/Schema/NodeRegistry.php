<?php

namespace Nuwave\Lighthouse\Schema;

use Closure;
use GraphQL\Error\Error;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

class NodeRegistry
{
    use HandlesGlobalId;

    /**
     * Registered nodes.
     *
     * @var array
     */
    protected $nodes = [];

    /**
     * Model to type map.
     *
     * @var array
     */
    protected $modelToTypeMap = [];

    /**
     * Value to type map.
     *
     * @var array
     */
    protected $valueToTypeMap = [];

    /**
     * Store resolver for node.
     *
     * @param string  $typeName
     * @param Closure $resolver
     * @param Closure $resolveType
     *
     * @return mixed
     */
    public function registerNode($typeName, Closure $resolver, Closure $resolveType)
    {
        $this->valueToTypeMap[$typeName] = $resolveType;
        $this->nodes[$typeName] = $resolver;
    }

    /**
     * Register model node.
     *
     * @param string $typeName
     * @param string $modelClassName
     */
    public function registerModel($typeName, $modelClassName)
    {
        $this->modelToTypeMap[$modelClassName] = $typeName;

        $this->nodes[$typeName] = function ($id) use ($modelClassName) {
            return $modelClassName::find($id);
        };
    }

    /**
     * Resolve node.
     *
     * @param string $globalId
     *
     * @return mixed
     */
    public function resolve($globalId)
    {
        $type = $this->decodeRelayType($globalId);

        if (! isset($this->nodes[$type])) {
            throw new Error('['.$type.'] is not a registered node and cannot be resolved.');
        }

        $resolver = $this->nodes[$type];

        return $resolver($this->decodeRelayId($globalId));
    }

    /**
     * Resolve node type.
     *
     * @param mixed $value
     *
     * @return \GraphQL\Type\Definition\Type
     */
    public function resolveType($value)
    {
        if (is_object($value) && isset($this->modelToTypeMap[get_class($value)])) {
            return types()->get($this->modelToTypeMap[get_class($value)]);
        }

        return collect($this->valueToTypeMap)
            ->map(function ($value, $key) {
                return ['resolver' => $value, 'type' => $key];
            })
            ->reduce(function ($instance, $item) use ($value) {
                if ($instance) {
                    return $instance;
                }

                $resolver = $item['resolver'];
                $type = $item['type'];

                return $resolver($value) ? types()->get($type) : $instance;
            });
    }
}
