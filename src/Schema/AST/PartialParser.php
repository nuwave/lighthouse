<?php

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Support\Exceptions\ParseException;

class PartialParser
{
    /**
     * @param string[] $objectTypes
     *
     * @return ObjectTypeDefinitionNode[]
     */
    public static function objectTypes($objectTypes)
    {
        return array_map(function ($objectType) {
            return self::objectType($objectType);
        }, $objectTypes);
    }

    /**
     * @param string $definition
     *
     * @throws ParseException
     *
     * @return ObjectTypeDefinitionNode
     */
    public static function objectType($definition)
    {
        return self::getFirstAndValidateType(
            Parser::parse($definition)->definitions,
            ObjectTypeDefinitionNode::class
        );
    }

    /**
     * @param string $inputValueDefinition
     *
     * @throws ParseException
     *
     * @return InputValueDefinitionNode
     */
    public static function inputValueDefinition($inputValueDefinition)
    {
        return self::getFirstAndValidateType(
            self::fieldDefinition("field($inputValueDefinition): String")->arguments,
            InputValueDefinitionNode::class
        );
    }

    /**
     * @param string[] $inputValueDefinitions
     *
     * @return InputValueDefinitionNode[]
     */
    public static function inputValueDefinitions($inputValueDefinitions)
    {
        return array_map(function ($inputValueDefinition) {
            return self::inputValueDefinition($inputValueDefinition);
        }, $inputValueDefinitions);
    }

    /**
     * @param string $argumentDefinition
     *
     * @throws ParseException
     *
     * @return NodeList
     */
    public static function argument($argumentDefinition)
    {
        return self::getFirstAndValidateType(
            self::field("field($argumentDefinition): String")->arguments,
            ArgumentNode::class
        );
    }

    /**
     * @param string[] $argumentDefinitions
     *
     * @return InputValueDefinitionNode[]
     */
    public static function arguments($argumentDefinitions)
    {
        return array_map(function ($argumentDefinition) {
            return self::argument($argumentDefinition);
        }, $argumentDefinitions);
    }

    /**
     * @param string $field
     *
     * @throws ParseException
     *
     * @return FieldNode
     */
    public static function field($field)
    {
        return self::getFirstAndValidateType(
            self::operationDefinition("{ $field }")->selectionSet->selections,
            FieldNode::class
        );
    }

    /**
     * @param string $operation
     *
     * @throws ParseException
     *
     * @return OperationDefinitionNode
     */
    public static function operationDefinition($operation)
    {
        return self::getFirstAndValidateType(
            Parser::parse($operation)->definitions,
            OperationDefinitionNode::class
        );
    }

    /**
     * @param string $fieldDefinition
     *
     * @throws ParseException
     *
     * @return FieldDefinitionNode
     */
    public static function fieldDefinition($fieldDefinition)
    {
        return self::getFirstAndValidateType(
            self::objectType("type Dummy { $fieldDefinition }")->fields,
            FieldDefinitionNode::class
        );
    }

    /**
     * @param string $directive
     *
     * @throws ParseException
     *
     * @return DirectiveNode
     */
    public static function directive($directive)
    {
        return self::getFirstAndValidateType(
            self::objectType("type Dummy $directive {}")->directives,
            DirectiveNode::class
        );
    }

    /**
     * @param string[] $directives
     *
     * @return DirectiveNode[]
     */
    public static function directives($directives)
    {
        return array_map(function ($directive) {
            return self::inputValueDefinition($directive);
        }, $directives);
    }

    /**
     * @param string $directiveDefinition
     *
     * @throws ParseException
     *
     * @return DirectiveDefinitionNode
     */
    public static function directiveDefinition($directiveDefinition)
    {
        return self::getFirstAndValidateType(
            Parser::parse($directiveDefinition)->definitions,
            DirectiveDefinitionNode::class
        );
    }

    /**
     * @param string[] $directiveDefinitions
     *
     * @return DirectiveDefinitionNode[]
     */
    public static function directiveDefinitions($directiveDefinitions)
    {
        return array_map(function ($directiveDefinition) {
            return self::inputValueDefinition($directiveDefinition);
        }, $directiveDefinitions);
    }

    /**
     * @param $interfaceDefinition
     *
     * @throws ParseException
     *
     * @return InterfaceTypeDefinitionNode
     */
    public static function interfaceTypeDefinition($interfaceDefinition)
    {
        return self::getFirstAndValidateType(
            Parser::parse($interfaceDefinition)->definitions,
            InterfaceTypeDefinitionNode::class
        );
    }

    /**
     * @param string $inputTypeDefinition
     *
     * @throws ParseException
     *
     * @return InputObjectTypeDefinitionNode
     */
    public static function inputObjectTypeDefinition($inputTypeDefinition)
    {
        return self::getFirstAndValidateType(
            Parser::parse($inputTypeDefinition)->definitions,
            InputObjectTypeDefinitionNode::class
        );
    }

    /**
     * @param string $scalarDefinition
     *
     * @throws ParseException
     *
     * @return ScalarTypeDefinitionNode
     */
    public static function scalarTypeDefinition($scalarDefinition)
    {
        return self::getFirstAndValidateType(
            Parser::parse($scalarDefinition)->definitions,
            ScalarTypeDefinitionNode::class
        );
    }

    /**
     * Get the first Node from a given NodeList and validate it.
     *
     * @param NodeList $list
     * @param string   $expectedType
     *
     * @throws ParseException
     *
     * @return mixed
     */
    protected static function getFirstAndValidateType(NodeList $list, $expectedType)
    {
        if (1 !== $list->count()) {
            throw new ParseException('  More than one definition was found in the passed in schema.');
        }

        $node = $list[0];

        if (! $node instanceof $expectedType) {
            throw new ParseException("The given definition was not of type: $expectedType");
        }

        return $node;
    }
}
