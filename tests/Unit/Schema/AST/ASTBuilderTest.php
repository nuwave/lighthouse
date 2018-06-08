<?php

namespace Tests\Unit\Schema\AST;

use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Tests\TestCase;

class ASTBuilderTest extends TestCase
{
    public function testMergeTypeExtensionFields()
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

        $this->assertCount(3, $ast->objectType('Query')->fields);
    }
}
