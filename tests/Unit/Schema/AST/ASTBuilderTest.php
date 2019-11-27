<?php

namespace Tests\Unit\Schema\AST;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Tests\TestCase;

class ASTBuilderTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\AST\ASTBuilder
     */
    protected $astBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->astBuilder = app(ASTBuilder::class);
    }

    public function testCanMergeTypeExtensionFields(): void
    {
        $this->schema = '
        type Query {
            foo: String
        }
        
        extend type Query {
            bar: Int!
        }
        
        extend type Query {
            baz: Boolean
        }
        ';
        $documentAST = $this->astBuilder->documentAST();

        $this->assertCount(
            3,
            $documentAST->types['Query']->fields
        );
    }

    public function testDoesNotAllowDuplicateFieldsOnTypeExtensions(): void
    {
        $this->schema = '
        type Query {
            foo: String
        }
        
        extend type Query {
            foo: Int
        }
        ';

        $this->expectException(DefinitionException::class);
        $this->astBuilder->documentAST();
    }
}
