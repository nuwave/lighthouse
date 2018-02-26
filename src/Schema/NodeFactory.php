<?php

namespace Nuwave\Lighthouse\Schema;

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

class NodeFactory
{
    /**
     * Resolve enum definition to type.
     *
     * @param EnumTypeDefinitionNode $enum
     *
     * @return \GraphQL\Type\Definition\EnumType
     */
    public static function enum(EnumTypeDefinitionNode $enum)
    {
        return count($enum->directives)
            ? directives()->forNode($enum)->resolve($enum)
            : EnumResolver::resolve($enum);
    }

    /**
     * Resolve scalar definition to type.
     *
     * @param ScalarTypeDefinitionNode $scalar
     *
     * @return \GraphQL\Type\Definition\ScalarType
     */
    public static function scalar(ScalarTypeDefinitionNode $scalar)
    {
        return count($scalar->directives)
            ? directives()->forNode($scalar)->resolve($scalar)
            : ScalarResolver::resolve($scalar);
    }

    /**
     * Resolve interface definition to type.
     *
     * @param InterfaceTypeDefinitionNode $interface
     *
     * @return \GraphQL\Type\Definition\InterfaceType
     */
    public static function interface(InterfaceTypeDefinitionNode $interface)
    {
        return count($interface->directives)
            ? directives()->forNode($interface)->resolve($interface)
            : InterfaceResolver::resolve($interface);
    }

    /**
     * Resolve object type definition to type.
     *
     * @param ObjectTypeDefinitionNode $objectType
     *
     * @return \GraphQL\Type\Definition\ObjectType
     */
    public static function objectType(ObjectTypeDefinitionNode $objectType)
    {
        return count($objectType->directives)
            ? directives()->forNode($objectType)->resolve($objectType)
            : ObjectTypeResolver::resolve($objectType);
    }

    /**
     * Resolve input type definition to type.
     *
     * @param InputObjectTypeDefinitionNode $inputType
     *
     * @return \GraphQL\Type\Definition\InputObjectType
     */
    public static function inputObjectType(InputObjectTypeDefinitionNode $inputType)
    {
        return count($inputType->directives)
            ? directives()->forNode($inputType)->resolve($inputType)
            : InputObjectTypeResolver::resolve($inputType);
    }
}
