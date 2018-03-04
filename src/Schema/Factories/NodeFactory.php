<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Resolvers\EnumResolver;
use Nuwave\Lighthouse\Schema\Resolvers\InputObjectTypeResolver;
use Nuwave\Lighthouse\Schema\Resolvers\InterfaceResolver;
use Nuwave\Lighthouse\Schema\Resolvers\ObjectTypeResolver;
use Nuwave\Lighthouse\Schema\Resolvers\ScalarResolver;
use Nuwave\Lighthouse\Schema\Values\NodeValue;

class NodeFactory
{
    /**
     * Resolve enum definition to type.
     *
     * @param NodeValue $value
     *
     * @return \GraphQL\Type\Definition\EnumType
     */
    public static function enum(NodeValue $value)
    {
        $type = directives()->hasNodeResolver($value->getNode())
            ? directives()->forNode($value->getNode())->resolve($value->getNode())
            : EnumResolver::resolve($value->getNode());

        return self::applyMiddleware($value->setType($type))->getType();
    }

    /**
     * Resolve scalar definition to type.
     *
     * @param NodeValue $value
     *
     * @return \GraphQL\Type\Definition\ScalarType
     */
    public static function scalar(NodeValue $value)
    {
        $type = directives()->hasNodeResolver($value->getNode())
            ? directives()->forNode($value->getNode())->resolve($value->getNode())
            : ScalarResolver::resolve($value->getNode());

        return self::applyMiddleware($value->setType($type))->getType();
    }

    /**
     * Resolve interface definition to type.
     *
     * @param NodeValue $value
     *
     * @return \GraphQL\Type\Definition\InterfaceType
     */
    public static function interface(NodeValue $value)
    {
        $type = directives()->hasNodeResolver($value->getNode())
            ? directives()->forNode($value->getNode())->resolve($value->getNode())
            : InterfaceResolver::resolve($value->getNode());

        return self::applyMiddleware($value->setType($type))->getType();
    }

    /**
     * Resolve object type definition to type.
     *
     * @param NodeValue $value
     *
     * @return \GraphQL\Type\Definition\ObjectType
     */
    public static function objectType(NodeValue $value)
    {
        $type = directives()->hasNodeResolver($value->getNode())
            ? directives()->forNode($value->getNode())->resolve($value->getNode())
            : ObjectTypeResolver::resolve($value->getNode());

        return self::applyMiddleware($value->setType($type))->getType();
    }

    /**
     * Resolve input type definition to type.
     *
     * @param NodeValue $value
     *
     * @return \GraphQL\Type\Definition\InputObjectType
     */
    public static function inputObjectType(NodeValue $value)
    {
        $type = directives()->hasNodeResolver($value->getNode())
            ? directives()->forNode($value->getNode())->resolve($value->getNode())
            : InputObjectTypeResolver::resolve($value->getNode());

        return self::applyMiddleware($value->setType($type))->getType();
    }

    /**
     * Apply node middleware.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public static function applyMiddleware(NodeValue $value)
    {
        return directives()->nodeMiddleware($value->getNode())
            ->reduce(function ($value, $middleware) {
                return $middleware->handle($value);
            }, $value);
    }
}
