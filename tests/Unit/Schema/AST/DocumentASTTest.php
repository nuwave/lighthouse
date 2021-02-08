<?php

namespace Tests\Unit\Schema\AST;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Exceptions\ParseException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\RootType;
use Tests\TestCase;

class DocumentASTTest extends TestCase
{
    public function testParsesSimpleSchema(): void
    {
        $documentAST = DocumentAST::fromSource(/** @lang GraphQL */ '
        type Query {
            foo: Int
        }
        ');

        $this->assertInstanceOf(
            ObjectTypeDefinitionNode::class,
            $documentAST->types[RootType::QUERY]
        );
    }

    public function testThrowsOnInvalidSchema(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Syntax Error: Unexpected Name "foo"');

        DocumentAST::fromSource('foo');
    }

    public function testOverwritesDefinitionWithSameName(): void
    {
        $documentAST = DocumentAST::fromSource(/** @lang GraphQL */ '
        type Query {
            foo: Int
        }
        ');

        $overwrite = Parser::objectTypeDefinition(/** @lang GraphQL */ '
        type Query {
            bar: Int
        }
        ');

        $documentAST->types[$overwrite->name->value] = $overwrite;

        $this->assertSame(
            $overwrite,
            $documentAST->types[RootType::QUERY]
        );
    }

    public function testCanBeSerialized(): void
    {
        $documentAST = DocumentAST::fromSource(/** @lang GraphQL */ '
        type Query {
            foo: Int
        }

        directive @foo on FIELD
        ');

        /** @var \Nuwave\Lighthouse\Schema\AST\DocumentAST $reserialized */
        $reserialized = unserialize(
            serialize($documentAST)
        );

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
        $queryType = $reserialized->types[RootType::QUERY];
        $this->assertInstanceOf(
            ObjectTypeDefinitionNode::class,
            $queryType
        );

        $this->assertInstanceOf(
            FieldDefinitionNode::class,
            $queryType->fields[0]
        );

        $this->assertInstanceOf(
            DirectiveDefinitionNode::class,
            $reserialized->directives['foo']
        );
    }
}
