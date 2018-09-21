<?php

namespace Tests\Unit\Schema\AST;

use Tests\TestCase;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\ArgumentNode;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Exceptions\ParseException;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;

class PartialParserTest extends TestCase
{
    public function testParseObjectType()
    {
        $this->assertInstanceOf(
            ObjectTypeDefinitionNode::class,
            PartialParser::objectTypeDefinition('
            type Foo {
                foo: String
            }
            ')
        );
    }

    public function testThrowsForInvalidDefinition()
    {
        $this->expectException(SyntaxError::class);
        PartialParser::objectTypeDefinition('
            INVALID
        ');
    }

    public function testThrowsIfMultipleDefinitionsAreGiven()
    {
        $this->expectException(ParseException::class);
        PartialParser::objectTypeDefinition('
        type Foo {
            foo: String
        }
        
        type Bar {
            bar: Int
        }
        ');
    }

    public function testThrowsIfDefinitionIsUnexpectedType()
    {
        $this->expectException(ParseException::class);
        PartialParser::objectTypeDefinition('
        interface Foo {
            foo: String
        }
        ');
    }

    public function testParsesObjectTypesArray()
    {
        $objectTypes = PartialParser::objectTypeDefinitions(['
        type Foo {
            foo: String
        }
        ', '
        type Bar {
            bar: Int
        }
        ']);

        $this->assertCount(2, $objectTypes);
        $this->assertInstanceOf(ObjectTypeDefinitionNode::class, $objectTypes[0]);
        $this->assertInstanceOf(ObjectTypeDefinitionNode::class, $objectTypes[1]);
    }

    public function testThrowsOnInvalidTypeInObjectTypesArray()
    {
        $this->expectException(ParseException::class);
        PartialParser::objectTypeDefinitions(['
        type Foo {
            foo: String
        }
        ', '
        interface Bar {
            bar: Int
        }
        ']);
    }

    public function testThrowsOnMultipleDefinitionsInArrayItem()
    {
        $this->expectException(ParseException::class);
        PartialParser::objectTypeDefinitions(['
        type Foo {
            foo: String
        }
        
        type Bar {
            bar: Int
        }
        ']);
    }

    public function testParseOperationDefinition()
    {
        $this->assertInstanceOf(
            OperationDefinitionNode::class,
            PartialParser::operationDefinition('
            {
                foo: Foo
            }
        ')
        );
    }

    public function testParseArgument()
    {
        $argumentNode = PartialParser::argument('key: "value"');

        $this->assertInstanceOf(
            ArgumentNode::class,
            $argumentNode
        );

        $this->assertEquals('key', $argumentNode->name->value);
        $this->assertEquals('value', $argumentNode->value->value);
    }
}
