<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;

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
     * A map from type names to resolver functions.
     *
     * @var \Closure[]
     */
    protected $nodeResolver = [];
    
    /**
     * The stashed current type.
     *
     * Since PHP resolves the fields synchronously and one after another,
     * we can safely stash just this one value. Should the need arise, this
     * can probably be a map from the unique field path to the type.
     *
     * @var string
     */
    protected $currentType;
    
    /**
     * @param string $typeName
     *
     * The name of the ObjectType that can be resolved with the Node interface
     * e.g. "User"
     *
     * @param \Closure $resolve
     *
     * A function that returns the actual value by ID, e.g.
     *
     * function($id, $context, ResolveInfo $info)
     * {
     *   return $this->db->getUserById($id)
     * }
     *
     * @return NodeRegistry
     */
    public function registerNode(string $typeName, \Closure $resolve): NodeRegistry
    {
        $this->nodeResolver[$typeName] = $resolve;

        return $this;
    }

    /**
     * Register an Eloquent model that can be resolved as a Node.
     *
     * @param string $typeName
     * @param string $modelName
     *
     * @return NodeRegistry
     */
    public function registerModel(string $typeName, string $modelName): NodeRegistry
    {
        $this->nodeResolver[$typeName] = function ($id) use ($modelName) {
            return $modelName::find($id);
        };
        
        return $this;
    }
    
    /**
     * Get the appropriate resolver for the node and call it with the decoded id.
     *
     * @param $rootValue
     * @param array $args
     * @param $context
     * @param ResolveInfo $resolveInfo
     *
     * @throws Error
     *
     * @return mixed
     */
    public function resolve($rootValue, $args, $context, ResolveInfo $resolveInfo)
    {
        list($decodedType, $decodedId) = $args['id'];

        // Check if we have a resolver registered for the given type
        if (! $resolver = array_get($this->nodeResolver, $decodedType)) {
            throw new Error("[{$decodedType}] is not a registered node and cannot be resolved.");
        }
        
        // Stash the decoded type, as it will later be used to determine the correct return type of the node query
        $this->currentType = $decodedType;
    
        return $resolver($decodedId, $context, $resolveInfo);
    }
    
    /**
     * Get the Type for the stashed type.
     *
     * @return Type
     */
    public function resolveType(): Type
    {
        return $this->typeRegistry->get($this->currentType);
    }
}
