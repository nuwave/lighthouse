<?php

namespace Nuwave\Lighthouse\Schema;

use Closure;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class NodeRegistry
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

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

    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * @param  string  $typeName
     *
     * The name of the ObjectType that can be resolved with the Node interface
     * e.g. "User"
     *
     * @param  \Closure  $resolve
     *
     * A function that returns the actual value by ID, e.g.
     *
     * function($id, GraphQLContext $context, ResolveInfo $resolveInfo)
     * {
     *   return $this->db->getUserById($id)
     * }
     *
     * @return $this
     */
    public function registerNode(string $typeName, Closure $resolve): self
    {
        $this->nodeResolver[$typeName] = $resolve;

        return $this;
    }

    /**
     * Register an Eloquent model that can be resolved as a Node.
     *
     * @deprecated use registerNode
     * @return $this
     */
    public function registerModel(string $typeName, string $modelName): self
    {
        $this->nodeResolver[$typeName] = function ($id) use ($modelName): ?Model {
            return $modelName::find($id);
        };

        return $this;
    }

    /**
     * Get the appropriate resolver for the node and call it with the decoded id.
     *
     * @param  array<string, mixed>  $args
     * @return mixed The result of calling the resolver.
     *
     * @throws \GraphQL\Error\Error
     */
    public function resolve($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        [$decodedType, $decodedId] = $args['id'];

        // Check if we have a resolver registered for the given type
        if (! $resolver = Arr::get($this->nodeResolver, $decodedType)) {
            throw new Error("[{$decodedType}] is not a registered node and cannot be resolved.");
        }

        // Stash the decoded type, as it will later be used to determine the correct return type of the node query
        $this->currentType = $decodedType;

        return $resolver($decodedId, $context, $resolveInfo);
    }

    /**
     * Get the Type for the stashed type.
     */
    public function resolveType(): Type
    {
        return $this->typeRegistry->get($this->currentType);
    }
}
