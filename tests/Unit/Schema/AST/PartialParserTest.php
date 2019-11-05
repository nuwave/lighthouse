<?php

namespace Tests\Unit\Schema\AST;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use Nuwave\Lighthouse\Exceptions\ParseException;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Tests\TestCase;

class PartialParserTest extends TestCase
{
    public function testParsesObjectType(): void
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

    public function testThrowsForInvalidDefinition(): void
    {
        $this->expectException(SyntaxError::class);
        PartialParser::objectTypeDefinition('
            INVALID
        ');
    }

    public function testThrowsIfMultipleDefinitionsAreGiven(): void
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

    public function testThrowsIfDefinitionIsUnexpectedType(): void
    {
        $this->expectException(ParseException::class);
        PartialParser::objectTypeDefinition('
        interface Foo {
            foo: String
        }
        ');
    }

    public function testParsesObjectTypesArray(): void
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

    public function testThrowsOnInvalidTypeInObjectTypesArray(): void
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

    public function testThrowsOnMultipleDefinitionsInArrayItem(): void
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

    public function testParsesOperationDefinition(): void
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

    public function testParsesArgument(): void
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
