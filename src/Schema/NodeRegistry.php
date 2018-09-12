<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Execution\Utils\GlobalId;

class NodeRegistry
{
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
    protected $nodeResolver = [];

    /**
     * Value to type map.
     *
     * @var array
     */
    protected $typeResolvers = [];
    
    /**
     * Model to type map.
     *
     * @var array
     */
    protected $modelToTypeMap = [];
    
    /**
     * Store resolver for node.
     *
     * @param string $type
     * @param \Closure $resolve
     * @param \Closure $resolveType
     *
     * @return NodeRegistry
     */
    public function node(string $type, \Closure $resolve, \Closure $resolveType): NodeRegistry
    {
        $this->nodeResolver[$type] = $resolve;
        $this->typeResolvers[$type] = $resolveType;
        
        return $this;
    }

    /**
     * Register model node.
     *
     * @param string $typeName
     * @param string $modelName
     *
     * @return NodeRegistry
     */
    public function model(string $typeName, string $modelName): NodeRegistry
    {
        $this->modelToTypeMap[$modelName] = $typeName;

        $this->nodeResolver[$typeName] = function ($id) use ($modelName) {
            return $modelName::find($id);
        };
        
        return $this;
    }

    /**
     * Get the appropriate resolver for the node and call it with the decoded id.
     *
     * @param array $args
     *
     * @throws Error
     *
     * @return mixed
     */
    public function resolve($rootValue, $args)
    {
        $globalId = $args['id'];
        $type = GlobalId::decodeType($globalId);

        if (! isset($this->nodeResolver[$type])) {
            throw new Error("[{$type}] is not a registered node and cannot be resolved.");
        }

        $resolver = $this->nodeResolver[$type];

        return $resolver(
            GlobalId::decodeID($globalId)
        );
    }

    /**
     * Determine the GraphQL type of a given value.
     *
     * @param mixed $value
     *
     * @return Type
     */
    public function resolveType($value): Type
    {
        // If the value is a class, check if it is in the registered models
        if (is_object($value) && $modelName = array_get($this->modelToTypeMap, get_class($value))) {
            return $this->typeRegistry->get($modelName);
        }

        return collect($this->typeResolvers)
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
