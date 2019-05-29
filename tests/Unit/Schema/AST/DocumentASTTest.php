<?php

namespace Tests\Unit\Schema\AST;

use Tests\TestCase;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Exceptions\ParseException;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;

class DocumentASTTest extends TestCase
{
    /**
     * @test
     */
    public function itParsesSimpleSchema(): void
    {
        $documentAST = DocumentAST::fromSource('
        type Query {
            foo: Int
        }
        ');

        $this->assertInstanceOf(
            ObjectTypeDefinitionNode::class,
            $documentAST->types['Query']
        );
    }

    /**
     * @test
     */
    public function itThrowsOnInvalidSchema(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessageRegExp('/^Syntax Error/');

        DocumentAST::fromSource('foo');
    }

    /**
     * @test
     */
    public function itOverwritesDefinitionWithSameName(): void
    {
        $documentAST = DocumentAST::fromSource('
        type Query {
            foo: Int
        }
        ');

        $overwrite = PartialParser::objectTypeDefinition('
        type Query {
            bar: Int
        }
        ');

        $documentAST->types[$overwrite->name->value] = $overwrite;

        $this->assertSame(
            $overwrite,
            $documentAST->types['Query']
        );
    }

    /**
     * @test
     */
    public function itCanBeSerialized(): void
    {
        $documentAST = DocumentAST::fromSource('
        type Query {
            foo: Int
        }

        directive @foo on FIELD
        ');

        /** @var DocumentAST $reserialized */
        $reserialized = unserialize(
            serialize($documentAST)
        );

        /** @var ObjectTypeDefinitionNode $queryType */
        $queryType = $reserialized->types['Query'];
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
