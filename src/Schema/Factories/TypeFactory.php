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
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Directives\Types\TypeMiddleware;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Schema\Resolvers\ScalarResolver;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesTypes;

class TypeFactory
{
    use HandlesDirectives, HandlesTypes;

    /**
     * Transform node to type.
     *
     * @param TypeValue $value
     *
     * @return Type
     */
    public function toType(TypeValue $value)
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
     * @param TypeValue $value
     *
     * @return bool
     */
    protected function hasTypeResolver(TypeValue $value)
    {
        return directives()->hasTypeResolver($value->getNode());
    }

    /**
     * Use directive resolver to transform type.
     *
     * @param TypeValue $value
     *
     * @return Type
     */
    protected function resolveTypeViaDirective(TypeValue $value)
    {
        return directives()
            ->typeResolverForNode($value->getNode())
            ->resolveType($value);
    }

    /**
     * Transform value to type.
     *
     * @param TypeValue $value
     *
     * @return Type
     */
    protected function resolveType(TypeValue $value)
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
            // todo deal with UnionTypes
            default:
                throw new \Exception("Unknown type for Node [{$value->getNodeName()}]");
        }
    }

    /**
     * Resolve enum definition to type.
     *
     * @param TypeValue $value
     *
     * @return EnumType
     */
    public function enum(TypeValue $value)
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
     * @param TypeValue $value
     *
     * @return ScalarType
     */
    public function scalar(TypeValue $value)
    {
        return ScalarResolver::resolveType($value);
    }

    /**
     * Resolve interface definition to type.
     *
     * @param TypeValue $value
     *
     * @return InterfaceType
     */
    public function interface(TypeValue $value)
    {
        return new InterfaceType([
            'name' => $value->getNodeName(),
            'fields' => $this->getFields($value),
        ]);
    }

    /**
     * Resolve object type definition to type.
     *
     * @param TypeValue $value
     *
     * @return ObjectType
     */
    public function objectType(TypeValue $value)
    {
        return new ObjectType([
            'name' => $value->getNodeName(),
            'fields' => function () use ($value) {
                return $this->getFields($value);
            },
            'interfaces' => function () use ($value) {
                return $value->getInterfaceNames()->map(function ($interfaceName) {
                    return schema()->instance($interfaceName);
                })->toArray();
            },
        ]);
    }

    /**
     * Resolve input type definition to type.
     *
     * @param TypeValue $value
     *
     * @return InputObjectType
     */
    public function inputObjectType(TypeValue $value)
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
     * @param TypeValue $value
     *
     * @return TypeValue
     */
    protected function applyMiddleware(TypeValue $value)
    {
        return directives()->nodeMiddleware($value->getNode())
            ->reduce(function (TypeValue $value, TypeMiddleware $middleware) {
                return $middleware->handleNode($value);
            }, $value);
    }
}
