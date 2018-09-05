<?php

namespace Tests\Unit\Schema\AST;

use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Tests\TestCase;

class ASTBuilderTest extends TestCase
{
    /**
     * @test
     */
    public function itCanMergeTypeExtensionFields()
    {
        $ast = ASTBuilder::generate('
        type Query {
            foo: String
        }
        
        extend type Query {
            bar: Int!
        }
        
        extend type Query {
            baz: Boolean
        }
        ');

        $this->assertCount(3, $ast->objectTypeDefinition('Query')->fields);
    }

    /**
     * @test
     */
    public function itCreatesAnImmutableDocumentAST()
    {
        $documentAST = ASTBuilder::generate('
        type User {
            email: String
        }
        ');

        $originalDocument = new DocumentAST($documentAST->document());

        $userType = $documentAST->objectTypeDefinition('User');
        $this->assertCount(1, $userType->fields);

        $dummyType = PartialParser::objectTypeDefinition('
        type DummyType {
            name: String
        }
        ');

        $userType->fields = ASTHelper::mergeNodeList(
            $userType->fields,
            $dummyType->fields
        );

        $this->assertCount(1, $documentAST->objectTypeDefinition('User')->fields);

        $documentAST->setDefinition($userType);
        $this->assertCount(2, $documentAST->objectTypeDefinition('User')->fields);
        $this->assertCount(1, $originalDocument->objectTypeDefinition('User')->fields);
    }
}
