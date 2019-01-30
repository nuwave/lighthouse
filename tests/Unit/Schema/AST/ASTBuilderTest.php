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

    protected function setUp()
    {
        parent::setUp();

        $this->astBuilder = app(ASTBuilder::class);
    }

    /**
     * @test
     */
    public function itCanMergeTypeExtensionFields(): void
    {
        $documentAST = $this->astBuilder->build('
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

    /**
     * @test
     */
    public function itDoesNotAllowDuplicateFieldsOnTypeExtensions(): void
    {
        $this->expectException(DefinitionException::class);
        $this->astBuilder->build('
        type Query {
            foo: String
        }
        
        extend type Query {
            foo: Int
        }
        ');
    }
}
