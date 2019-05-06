<?php

namespace Tests\Unit\Schema\AST;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

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

    /**
     * @test
     */
    public function itCanMergeTypeExtensionFields(): void
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
        $documentAST = $this->astBuilder->build();

        $this->assertCount(
            3,
            $documentAST->types['Query']->fields
        );
    }

    /**
     * @test
     */
    public function itDoesNotAllowDuplicateFieldsOnTypeExtensions(): void
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
        $this->astBuilder->build();
    }
}
