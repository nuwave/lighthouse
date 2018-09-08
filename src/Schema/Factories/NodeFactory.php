<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Support\Traits\HandlesTypes;
use Nuwave\Lighthouse\Schema\Directives\Nodes\EnumDirective;
use Nuwave\Lighthouse\Schema\Directives\Nodes\UnionDirective;
use Nuwave\Lighthouse\Schema\Directives\Nodes\ScalarDirective;

class NodeFactory
{
    use HandlesTypes;

    /** @var DirectiveRegistry */
    protected $directiveRegistry;
    /** @var TypeRegistry */
    protected $typeRegistry;
    /** @var Pipeline */
    protected $pipeline;

    /**
     * @param DirectiveRegistry $directiveRegistry
     * @param TypeRegistry $typeRegistry
     * @param Pipeline $pipeline
     */
    public function __construct(DirectiveRegistry $directiveRegistry, TypeRegistry $typeRegistry, Pipeline $pipeline)
    {
        $this->directiveRegistry = $directiveRegistry;
        $this->typeRegistry = $typeRegistry;
        $this->pipeline = $pipeline;
    }

    /**
     * Transform node to type.
     *
     * @param NodeValue $value
     *
     * @throws \Exception
     *
     * @return Type
     */
    public function handle(NodeValue $value): Type
    {
        $value->setType(
            $this->hasTypeResolver($value)
                ? $this->resolveTypeViaDirective($value)
                : $this->resolveType($value)
        );

        return $this->applyMiddleware($value)->getType();
    }

    /**
     * Check if node has a type resolver directive.
     *
     * @param NodeValue $value
     *
     * @return bool
     */
    protected function hasTypeResolver(NodeValue $value): bool
    {
        return $this->directiveRegistry->hasNodeResolver($value->getNode());
    }

    /**
     * Use directive resolver to transform type.
     *
     * @param NodeValue $value
     *
     * @return Type
     */
    protected function resolveTypeViaDirective(NodeValue $value): Type
    {
        return $this->directiveRegistry
            ->nodeResolver($value->getNode())
            ->resolveNode($value);
    }

    /**
     * Transform value to type.
     *
     * @param NodeValue $value
     *
     * @throws \Exception
     *
     * @return Type
     */
    protected function resolveType(NodeValue $value): Type
    {
        // We do not have to consider TypeExtensionNode since they
        // are merged before we get here
        switch (\get_class($value->getNode())) {
            case EnumTypeDefinitionNode::class:
                return $this->enum($value);
            case ScalarTypeDefinitionNode::class:
                return $this->scalar($value);
            case InterfaceTypeDefinitionNode::class:
                return $this->interface($value);
            case ObjectTypeDefinitionNode::class:
                return $this->objectType($value);
            case InputObjectTypeDefinitionNode::class:
                return $this->inputObjectType($value);
            case UnionTypeDefinitionNode::class:
                return $this->union($value);
            default:
                throw new \Exception("Unknown type for Node [{$value->getNodeName()}]");
        }
    }

    /**
     * Resolve enum definition to type.
     *
     * @param NodeValue $enumNodeValue
     *
     * @return EnumType
     */
    public function enum(NodeValue $enumNodeValue): EnumType
    {
        $enumDirective = (new EnumDirective())->hydrate($enumNodeValue->getNode());

        return $enumDirective->resolveNode($enumNodeValue);
    }

    /**
     * Resolve scalar definition to type.
     *
     * @param NodeValue $scalarNodeValue
     *
     * @return ScalarType
     */
    protected function scalar(NodeValue $scalarNodeValue): ScalarType
    {
        $scalarDirective = (new ScalarDirective())->hydrate($scalarNodeValue->getNode());

        return $scalarDirective->resolveNode($scalarNodeValue);
    }

    /**
     * Resolve interface definition to type.
     *
     * @param NodeValue $value
     *
     * @return InterfaceType
     */
    protected function interface(NodeValue $value): InterfaceType
    {
        return new InterfaceType([
            'name' => $value->getNodeName(),
            'fields' => $this->getFields($value),
        ]);
    }

    /**
     * Resolve object type definition to type.
     *
     * @param NodeValue $value
     *
     * @return ObjectType
     */
    protected function objectType(NodeValue $value): ObjectType
    {
        return new ObjectType([
            'name' => $value->getNodeName(),
            'fields' => function () use ($value) {
                return $this->getFields($value);
            },
            'interfaces' => function () use ($value) {
                return $value->getInterfaceNames()->map(function ($interfaceName) {
                    return $this->typeRegistry->get($interfaceName);
                })->toArray();
            },
        ]);
    }

    /**
     * Resolve input type definition to type.
     *
     * @param NodeValue $value
     *
     * @return InputObjectType
     */
    protected function inputObjectType(NodeValue $value): InputObjectType
    {
        return new InputObjectType([
            'name' => $value->getNodeName(),
            'fields' => function () use ($value) {
                return $this->getFields($value);
            },
        ]);
    }

    /**
     * Resolve union type definition to type.
     *
     * @param NodeValue $value
     *
     * @return UnionType
     */
    protected function union(NodeValue $value): UnionType
    {
        $unionDirective = (new UnionDirective())->hydrate($value->getNode());

        return $unionDirective->resolveNode($value);
    }

    /**
     * Apply node middleware.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    protected function applyMiddleware(NodeValue $value): NodeValue
    {
        return $this->pipeline
            ->send($value)
            ->through($this->directiveRegistry->nodeMiddleware($value->getNode()))
            ->via('handleNode')
            ->then(function (NodeValue $value) {
                return $value;
            });
    }
}
