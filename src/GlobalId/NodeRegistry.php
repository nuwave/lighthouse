<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\GlobalId;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * @phpstan-type NodeResolverFn callable(mixed $id, \Nuwave\Lighthouse\Support\Contracts\GraphQLContext $context, \Nuwave\Lighthouse\Execution\ResolveInfo $resolveInfo): mixed
 */
class NodeRegistry
{
    /**
     * A map from type names to resolver functions.
     *
     * @var array<string, NodeResolverFn>
     */
    protected array $nodeResolverFns = [];

    /**
     * The stashed current type.
     *
     * Since PHP resolves the fields synchronously and one after another,
     * we can safely stash just this one value. Should the need arise, this
     * can probably be a map from the unique field path to the type.
     */
    protected string $currentType;

    public function __construct(
        protected TypeRegistry $typeRegistry,
    ) {}

    /**
     * @param  string  $typeName  The name of the ObjectType that can be resolved with the Node interface
     * @param  NodeResolverFn  $resolver  A function that returns the actual value by ID
     *
     * @example "User"
     * @example fn($id, GraphQLContext $context, ResolveInfo $resolveInfo) => $this->db->getUserById($id)
     */
    public function registerNode(string $typeName, callable $resolver): self
    {
        $this->nodeResolverFns[$typeName] = $resolver;

        return $this;
    }

    /**
     * Get the appropriate resolver for the node and call it with the decoded id.
     *
     * @param  array<string, mixed>  $args
     */
    public function resolve(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): mixed
    {
        [$decodedType, $decodedId] = $args['id'];

        // This check forces Lighthouse to eagerly load the type, which might not have
        // happened if the client only references it indirectly through an interface.
        // Loading the type in turn causes the TypeMiddleware to run and thus register the type in the NodeRegistry.
        $this->typeRegistry->has($decodedType)
            ?: throw new Error("[{$decodedType}] is not a type and cannot be resolved.");
        // We can not continue without a resolver.
        $resolver = $this->nodeResolverFns[$decodedType]
            ?? throw new Error("[{$decodedType}] is not a registered node and cannot be resolved.");

        // Stash the decoded type, as it will later be used to determine the correct return type of the node query
        $this->currentType = $decodedType;

        return $resolver($decodedId, $context, $resolveInfo);
    }

    /** Get the Type for the stashed type. */
    public function resolveType(): Type
    {
        return $this->typeRegistry->get($this->currentType);
    }
}
