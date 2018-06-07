<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Directives\Nodes\NodeMiddleware;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Schema\Resolvers\ScalarResolver;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
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
     */
    public function handle(NodeValue $value)
    {
        $value = $this->hasResolver($value)
            ? $this->useResolver($value)
            : $this->transform($value);

        return $this->applyMiddleware($value)->getType();
    }

    /**
     * Check if node has a resolver directive.
     *
     * @param NodeValue $value
     *
     * @return bool
     */
    protected function hasResolver(NodeValue $value)
    {
        return directives()->hasNodeResolver($value->getNode());
    }

    /**
     * Use directive resolver to transform type.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    protected function useResolver(NodeValue $value)
    {
        return directives()->forNode($value->getNode())
            ->resolveNode($value);
    }

    /**
     * Transform value to type.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    protected function transform(NodeValue $value)
    {
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
            case DirectiveDefinitionNode::class:
                return $this->clientDirective($value);
            default:
                throw new \Exception("Unknown node [{$value->getNodeName()}]");
        }
    }

    /**
     * Resolve enum definition to type.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function enum(NodeValue $value)
    {
        $enum = new EnumType([
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

        return $value->setType($enum);
    }

    /**
     * Resolve scalar definition to type.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function scalar(NodeValue $value)
    {
        return ScalarResolver::resolve($value);
    }

    /**
     * Resolve interface definition to type.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function interface(NodeValue $value)
    {
        $interface = new InterfaceType([
            'name' => $value->getNodeName(),
            'fields' => $this->getFields($value),
        ]);

        return $value->setType($interface);
    }

    /**
     * Resolve object type definition to type.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function objectType(NodeValue $value)
    {
        $objectType = new ObjectType([
            'name' => $value->getNodeName(),
            'fields' => function () use ($value) {
                return $this->getFields($value);
            },
            'interfaces' => function () use ($value) {
                return $value->getInterfaceNames()->map(function ($interfaceName) {
                    return schema()->instance($interfaceName);
                })->toArray();
            }
        ]);

        return $value->setType($objectType);
    }

    /**
     * Resolve input type definition to type.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function inputObjectType(NodeValue $value)
    {
        $inputType = new InputObjectType([
            'name' => $value->getNodeName(),
            'fields' => function () use ($value) {
                return $this->getFields($value);
            },
        ]);

        return $value->setType($inputType);
    }

    /**
     * Resolve client directive.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function clientDirective(NodeValue $value)
    {
        $node = $value->getNode();
        $args = $node->arguments
            ? collect($node->arguments)->map(function ($input) {
                return new FieldArgument([
                    'name' => data_get($input, 'name.value'),
                    'defaultValue' => data_get($input, 'defaultValue.value'),
                    'description' => data_get($input, 'description'),
                    'type' => NodeResolver::resolve(data_get($input, 'type')),
                ]);
            })->toArray()
            : null;

        $directive = new Directive([
            'name' => $node->name->value,
            'locations' => collect($node->locations)->map(function ($location) {
                return $location->value;
            })->toArray(),
            'args' => $args,
            'astNode' => $node,
        ]);

        return $value->setType($directive);
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
        return directives()->nodeMiddleware($value->getNode())
            ->reduce(function (NodeValue $value, NodeMiddleware $middleware) {
                return $middleware->handleNode($value);
            }, $value);
    }
}
