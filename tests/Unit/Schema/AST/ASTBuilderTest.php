<?php

namespace Tests\Unit\Schema\AST;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;

class ASTBuilderTest extends TestCase
{
    /**
     * @test
     */
    public function itCanMergeTypeExtensionFields()
    {
        $documentAST = ASTBuilder::generate('
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

        $this->assertCount(
            3,
            $documentAST
                ->queryTypeDefinition()
                ->fields
        );
    }
}
