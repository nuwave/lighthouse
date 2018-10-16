<?php

namespace Tests\Unit\Schema\AST;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Exceptions\ParseException;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;

class DocumentASTTest extends TestCase
{
    /**
     * @test
     */
    public function itParsesSimpleSchema()
    {
        $documentAST = DocumentAST::fromSource('
        type Query {
            foo: Int
        }
        ');

        $this->assertInstanceOf(
            ObjectTypeDefinitionNode::class,
            $documentAST->queryTypeDefinition()
        );
    }

    /**
     * @test
     */
    public function itThrowsOnInvalidSchema()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessageRegExp('/^Syntax Error/');
        
        DocumentAST::fromSource('foo');
    }

    /**
     * @test
     */
    public function itCanSetDefinition()
    {
        $documentAST = DocumentAST::fromSource('
        type Query {
            foo: Int
        }
        ');
        
        $objectType = PartialParser::objectTypeDefinition('
        type Mutation {
            bar: Int
        }
        ');
        
        $documentAST->setDefinition($objectType);
        
        $this->assertInstanceOf(
            ObjectTypeDefinitionNode::class,
            $documentAST->mutationTypeDefinition()
        );
    }
    /**
     * @test
     */
    public function itCanBeSerialized()
    {
        $documentAST = DocumentAST::fromSource('
        type Query {
            foo: Int
        }
        ');
        
        $reserialized = \unserialize(
            \serialize($documentAST)
        );
    
        $this->assertInstanceOf(
            ObjectTypeDefinitionNode::class,
            $reserialized->queryTypeDefinition()
        );
    
        $this->assertInstanceOf(
            FieldDefinitionNode::class,
            $reserialized->queryTypeDefinition()->fields[0]
        );
    }
}
