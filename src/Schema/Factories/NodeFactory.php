<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionDefinitionNode as Extension;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
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

        return $this->applyMiddleware($this->attachInterfaces($value))
            ->getType();
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
            ->resolve($value);
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
            case Extension::class:
                return $this->extend($value);
            default:
                throw new \Exception("Unknown node [{$value->getNodeName()}]");
        }
    }

    /**
     * Resolve enum definition to type.
     *
     * @param NodeValue $value
     *
     * @return \GraphQL\Type\Definition\EnumType
     */
    public function enum(NodeValue $value)
    {
        $enum = new EnumType([
            'name' => $value->getNodeName(),
            'values' => collect($value->getNode()->values)
                ->mapWithKeys(function (EnumValueDefinitionNode $field) {
                    $directive = $this->fieldDirective($field, 'enum');

                    if (! $directive) {
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
     * @return \GraphQL\Type\Definition\ScalarType
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
     * Extend type definition.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function extend(NodeValue $value)
    {
        $value->setNode(
            $value->getNode()->definition
        );

        $type = $value->getType();
        $originalFields = value($type->config['fields']);
        $type->config['fields'] = function () use ($originalFields, $value) {
            return array_merge($originalFields, $this->getFields($value));
        };

        return $value;
    }

    /**
     * Attach interfaces to type.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    protected function attachInterfaces(NodeValue $value)
    {
        $type = $value->getType();
        $type->config['interfaces'] = function () use ($value) {
            return collect($value->getInterfaces())->map(function ($interface) {
                return schema()->instance($interface);
            })->filter()->toArray();
        };

        return $value;
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
            ->reduce(function ($value, $middleware) {
                return $middleware->handle($value);
            }, $value);
    }
}
