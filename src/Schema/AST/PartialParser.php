<?php

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Exceptions\ParseException;

class PartialParser
{
    /**
     * @param  array<string>  $objectTypes
     * @return array<\GraphQL\Language\AST\ObjectTypeDefinitionNode>
     */
    public static function objectTypeDefinitions(array $objectTypes): array
    {
        return array_map(function ($objectType): ObjectTypeDefinitionNode {
            return self::objectTypeDefinition($objectType);
        }, $objectTypes);
    }

    public static function objectTypeDefinition(string $definition): ObjectTypeDefinitionNode
    {
        return self::getFirstAndValidateType(
            self::parse($definition)->definitions,
            ObjectTypeDefinitionNode::class
        );
    }

    public static function inputValueDefinition(string $inputValueDefinition): InputValueDefinitionNode
    {
        return self::getFirstAndValidateType(
            self::fieldDefinition("field($inputValueDefinition): String")->arguments,
            InputValueDefinitionNode::class
        );
    }

    /**
     * @param  string[]  $inputValueDefinitions
     * @return \GraphQL\Language\AST\InputValueDefinitionNode[]
     */
    public static function inputValueDefinitions(array $inputValueDefinitions): array
    {
        return array_map(
            function (string $inputValueDefinition): InputValueDefinitionNode {
                return self::inputValueDefinition($inputValueDefinition);
            },
            $inputValueDefinitions
        );
    }

    public static function argument(string $argumentDefinition): ArgumentNode
    {
        return self::getFirstAndValidateType(
            self::field("field($argumentDefinition)")->arguments,
            ArgumentNode::class
        );
    }

    /**
     * @param  array<string>  $argumentDefinitions
     * @return array<\GraphQL\Language\AST\ArgumentNode>
     */
    public static function arguments(array $argumentDefinitions): array
    {
        return array_map(
            function (string $argumentDefinition): ArgumentNode {
                return self::argument($argumentDefinition);
            },
            $argumentDefinitions
        );
    }

    public static function field(string $field): FieldNode
    {
        return self::getFirstAndValidateType(
            self::operationDefinition("{ $field }")->selectionSet->selections,
            FieldNode::class
        );
    }

    public static function operationDefinition(string $operation): OperationDefinitionNode
    {
        return self::getFirstAndValidateType(
            self::parse($operation)->definitions,
            OperationDefinitionNode::class
        );
    }

    public static function fieldDefinition(string $fieldDefinition): FieldDefinitionNode
    {
        return self::getFirstAndValidateType(
            self::objectTypeDefinition("type Dummy { $fieldDefinition }")->fields,
            FieldDefinitionNode::class
        );
    }

    public static function directive(string $directive): DirectiveNode
    {
        return self::getFirstAndValidateType(
            self::objectTypeDefinition("
            type Dummy $directive {
                dummy: Int
            }
            ")->directives,
            DirectiveNode::class
        );
    }

    /**
     * @param  array<string>  $directives
     * @return array<\GraphQL\Language\AST\DirectiveNode>
     */
    public static function directives(array $directives): array
    {
        return array_map(
            function (string $directive): DirectiveNode {
                return self::directive($directive);
            },
            $directives
        );
    }

    public static function directiveDefinition(string $directiveDefinition): DirectiveDefinitionNode
    {
        return self::getFirstAndValidateType(
            self::parse($directiveDefinition)->definitions,
            DirectiveDefinitionNode::class
        );
    }

    /**
     * @param  array<string>  $directiveDefinitions
     * @return array<\GraphQL\Language\AST\DirectiveDefinitionNode>
     */
    public static function directiveDefinitions(array $directiveDefinitions): array
    {
        return array_map(
            function (string $directiveDefinition): DirectiveDefinitionNode {
                return self::directiveDefinition($directiveDefinition);
            },
            $directiveDefinitions
        );
    }

    public static function interfaceTypeDefinition(string $interfaceDefinition): InterfaceTypeDefinitionNode
    {
        return self::getFirstAndValidateType(
            self::parse($interfaceDefinition)->definitions,
            InterfaceTypeDefinitionNode::class
        );
    }

    public static function unionTypeDefinition(string $unionDefinition): UnionTypeDefinitionNode
    {
        return self::getFirstAndValidateType(
            self::parse($unionDefinition)->definitions,
            UnionTypeDefinitionNode::class
        );
    }

    public static function inputObjectTypeDefinition(string $inputTypeDefinition): InputObjectTypeDefinitionNode
    {
        return self::getFirstAndValidateType(
            self::parse($inputTypeDefinition)->definitions,
            InputObjectTypeDefinitionNode::class
        );
    }

    public static function scalarTypeDefinition(string $scalarDefinition): ScalarTypeDefinitionNode
    {
        return self::getFirstAndValidateType(
            self::parse($scalarDefinition)->definitions,
            ScalarTypeDefinitionNode::class
        );
    }

    public static function enumTypeDefinition(string $enumDefinition): EnumTypeDefinitionNode
    {
        return self::getFirstAndValidateType(
            self::parse($enumDefinition)->definitions,
            EnumTypeDefinitionNode::class
        );
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\ParseException
     */
    public static function namedType(string $typeName): NamedTypeNode
    {
        return self::validateType(
            self::parseType($typeName),
            NamedTypeNode::class
        );
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\ParseException
     */
    public static function listType(string $typeDefinition): ListTypeNode
    {
        return self::validateType(
            self::parseType($typeDefinition),
            ListTypeNode::class
        );
    }

    protected static function parse(string $definition): DocumentNode
    {
        // Ignore location since it only bloats the AST
        return Parser::parse($definition, ['noLocation' => true]);
    }

    protected static function parseType(string $definition): Node
    {
        // Ignore location since it only bloats the AST
        return Parser::parseType($definition, ['noLocation' => true]);
    }

    /**
     * Get the first Node from a given NodeList and validate it.
     *
     *
     * @throws \Nuwave\Lighthouse\Exceptions\ParseException
     */
    protected static function getFirstAndValidateType(NodeList $list, string $expectedType): Node
    {
        if ($list->count() !== 1) {
            throw new ParseException('More than one definition was found in the passed in schema.');
        }

        $node = $list[0];

        return self::validateType($node, $expectedType);
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\ParseException
     */
    protected static function validateType(Node $node, string $expectedType): Node
    {
        if (! $node instanceof $expectedType) {
            throw new ParseException("The given definition was not of type: $expectedType");
        }

        return $node;
    }
}
