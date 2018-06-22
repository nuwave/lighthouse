<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Resolvers\ScalarResolver;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesTypes;

class NodeFactory
{
    use HandlesDirectives, HandlesTypes;

    /**
     * Transform node to type.
     *
     * @param NodeValue $value
     *
     * @return Type
     * @throws \Exception
     */
    public function handle(NodeValue $value)
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
    protected function hasTypeResolver(NodeValue $value)
    {
        return graphql()->directives()->hasNodeResolver($value->getNode());
    }

    /**
     * Use directive resolver to transform type.
     *
     * @param NodeValue $value
     *
     * @return Type
     */
    protected function resolveTypeViaDirective(NodeValue $value)
    {
        return graphql()->directives()
            ->forNode($value->getNode())
            ->resolveNode($value)
            ->getType();
    }

    /**
     * Transform value to type.
     *
     * @param NodeValue $value
     *
     * @return Type
     */
    protected function resolveType(NodeValue $value)
    {
        // We do not have to consider TypeExtensionNode since they
        // are merged before we get here
        switch (get_class($value->getNode())) {
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
                throw new \Exception('Union types need to have the @union directive defined to resolve them.');
            default:
                throw new \Exception("Unknown type for Node [{$value->getNodeName()}]");
        }
    }

    /**
     * Resolve enum definition to type.
     *
     * @param NodeValue $value
     *
     * @return EnumType
     */
    protected function enum(NodeValue $value)
    {
        return new EnumType([
            'name' => $value->getNodeName(),
            'values' => collect($value->getNode()->values)
                ->mapWithKeys(function (EnumValueDefinitionNode $field) {
                    $directive = $this->fieldDirective($field, 'enum');

                    if (!$directive) {
                        return [];
                    }

                    return [$field->name->value => [
                        'value' => $this->directiveArgValue($directive, 'value'),
                        'description' => $this->safeDescription($field->description),
                    ]];
                })->toArray(),
        ]);
    }

    /**
     * Resolve scalar definition to type.
     *
     * @param NodeValue $value
     *
     * @return ScalarType
     */
    protected function scalar(NodeValue $value)
    {
        return ScalarResolver::resolve($value)->getType();
    }

    /**
     * Resolve interface definition to type.
     *
     * @param NodeValue $value
     *
     * @return InterfaceType
     */
    protected function interface(NodeValue $value)
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
    protected function objectType(NodeValue $value)
    {
        return new ObjectType([
            'name' => $value->getNodeName(),
            'fields' => function () use ($value) {
                return $this->getFields($value);
            },
            'interfaces' => function () use ($value) {
                return $value->getInterfaceNames()->map(function ($interfaceName) {
                    return graphql()->types()->get($interfaceName);
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
    protected function inputObjectType(NodeValue $value)
    {
        return new InputObjectType([
            'name' => $value->getNodeName(),
            'fields' => function () use ($value) {
                return $this->getFields($value);
            },
        ]);
    }

    /**
     * Apply node middleware.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    protected function applyMiddleware(NodeValue $value)
    {
        return graphql()->directives()->nodeMiddleware($value->getNode())
            ->reduce(function (NodeValue $value, NodeMiddleware $middleware) {
                return $middleware->handleNode($value);
            }, $value);
    }
}
