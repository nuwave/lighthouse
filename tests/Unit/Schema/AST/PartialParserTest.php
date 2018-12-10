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
    /**
     * @test
     */
    public function itParsesObjectType()
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

    /**
     * @test
     */
    public function itThrowsForInvalidDefinition()
    {
        $this->expectException(SyntaxError::class);
        PartialParser::objectTypeDefinition('
            INVALID
        ');
    }

    /**
     * @test
     */
    public function itThrowsIfMultipleDefinitionsAreGiven()
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

    /**
     * @test
     */
    public function itThrowsIfDefinitionIsUnexpectedType()
    {
        $this->expectException(ParseException::class);
        PartialParser::objectTypeDefinition('
        interface Foo {
            foo: String
        }
        ');
    }

    /**
     * @test
     */
    public function itParsesObjectTypesArray()
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

    /**
     * @test
     */
    public function itThrowsOnInvalidTypeInObjectTypesArray()
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

    /**
     * @test
     */
    public function itThrowsOnMultipleDefinitionsInArrayItem()
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

    /**
     * @test
     */
    public function itParsesOperationDefinition()
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

    /**
     * @test
     */
    public function itParsesArgument()
    {
        $argumentNode = PartialParser::argument('key: "value"');

        $this->assertInstanceOf(
            ArgumentNode::class,
            $argumentNode
        );

        $this->assertSame('key', $argumentNode->name->value);
        $this->assertSame('value', $argumentNode->value->value);
    }
}
