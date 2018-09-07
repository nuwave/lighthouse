<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

class NodeRegistry
{
    use HandlesGlobalId;

    /** @var TypeRegistry */
    protected $typeRegistry;

    /**
     * NodeRegistry constructor.
     * @param TypeRegistry $typeRegistry
     */
    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

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
     * @param \Closure $resolver
     * @param \Closure $resolveType
     */
    public function node($type, \Closure $resolver, \Closure $resolveType)
    {
        $this->types[$type] = $resolveType;
        $this->nodes[$type] = $resolver;
    }

    /**
     * Register model node.
     *
     * @param string $type
     * @param string $model
     *
     * @return void
     */
    public function model($type, $model)
    {
        $this->models[$model] = $type;

        $this->nodes[$type] = function ($id) use ($model) {
            return $model::find($id);
        };
    }

    /**
     * Get the appropriate resolver for the node and call it with the decoded id.
     *
     * @param string $globalId
     *
     * @throws Error
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
     * Determine the GraphQL type of a given value.
     *
     * @param mixed $value
     *
     * @return \GraphQL\Type\Definition\Type
     */
    public function resolveType($value)
    {
        if (is_object($value) && $modelName = array_get($this->models, get_class($value))) {
            return $this->typeRegistry->get($modelName);
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

                return $resolver($value)
                    ? $this->typeRegistry->get($type)
                    : $instance;
            });
    }
}
