<?php

namespace Nuwave\Lighthouse\Schema;

use Closure;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

class NodeContainer
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
    protected $models = [];

    /**
     * Value to type map.
     *
     * @var array
     */
    protected $types = [];

    /**
     * Store resolver for node.
     *
     * @param string  $type
     * @param Closure $resolver
     * @param Closure $resolveType
     */
    public function node(string $type, Closure $resolver, Closure $resolveType)
    {
        $this->types[$type] = $resolveType;
        $this->nodes[$type] = $resolver;
    }

    /**
     * Register model node.
     *
     * @param string $type
     * @param string $model
     */
    public function model(string $type, string $model)
    {
        $this->models[$model] = $type;

        $this->nodes[$type] = function ($id) use ($model) {
            return $model::find($id);
        };
    }

    /**
     * Resolve node.
     *
     * @param string $globalId
     *
     * @return mixed
     * @throws Error
     */
    public function resolve(string $globalId)
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
     * @return Type
     */
    public function resolveType($value): Type
    {
        if (is_object($value) && isset($this->models[get_class($value)])) {
            return graphql()->types()->get($this->models[get_class($value)]);
        }

        return collect($this->types)
            ->map(function ($value, $key) {
                return ['resolver' => $value, 'type' => $key];
            })
            ->reduce(function ($instance, $item) use ($value) {
                if ($instance) {
                    return $instance;
                }

                $resolver = $item['resolver'];
                $type = $item['type'];

                return $resolver($value) ? graphql()->types()->get($type) : $instance;
            });
    }
}
