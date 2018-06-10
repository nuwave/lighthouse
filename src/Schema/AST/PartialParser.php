<?php

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
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
     * Parse a single object type.
     *
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
     * @param NodeList $list
     * @param string $expectedType
     *
     * @return mixed
     *
     * @throws ParseException
     */
    protected static function getFirstAndValidateType(NodeList $list, $expectedType)
    {
        if (1 !== $list->count()) {
            throw new ParseException('  More than one definition was found in the passed in schema.');
        }
        
        $node = $list[0];
        
        if (!$node instanceof $expectedType) {
            throw new ParseException("The given definition was not of type: $expectedType");
        }
        
        return $node;
    }
    
    /**
     * Parse the definition for arguments on a field.
     *
     * @param string $argumentDefinitions
     *
     * @throws \Exception
     *
     * @return NodeList
     */
    public static function argument($argumentDefinition)
    {
        return self::getFirstAndValidateType(
            self::fieldDefinition("field($argumentDefinition): String")->arguments,
            InputValueDefinitionNode::class
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
     * Parse a single field definition.
     *
     * @param $fieldDefinition
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
     * Parses a directive node attached to a type.
     *
     * @param string $directive
     *
     * @throws \Exception
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
    public static function directives($directivea)
    {
        return array_map(function ($directive) {
            return self::argument($directive);
        }, $directivea);
    }
    
    /**
     * Parse the definition for directives.
     *
     * @param string $directiveDefinition
     *
     * @throws \Exception
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
            return self::argument($directiveDefinition);
        }, $directiveDefinitions);
    }
    
    /**
     * Parse the definition for a single interface.
     *
     * @param $interfaceDefinition
     *
     * @return InterfaceTypeDefinitionNode
     */
    public static function interfaceType($interfaceDefinition)
    {
        return self::getFirstAndValidateType(
            Parser::parse($interfaceDefinition)->definitions,
            InterfaceTypeDefinitionNode::class
        );
    }
}
