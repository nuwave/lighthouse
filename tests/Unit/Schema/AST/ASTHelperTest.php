<?php

namespace Tests\Unit\Schema\AST;

use Tests\TestCase;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

class ASTHelperTest extends TestCase
{
    /**
     * @test
     */
    public function itThrowsWhenMergingUniqueNodeListWithCollision(): void
    {
        $objectType1 = PartialParser::objectTypeDefinition('
        type User {
            email: String
        }
        ');

        $objectType2 = PartialParser::objectTypeDefinition('
        type User {
            email(bar: String): Int
        }
        ');

        $this->expectException(DefinitionException::class);

        $objectType1->fields = ASTHelper::mergeUniqueNodeList(
            $objectType1->fields,
            $objectType2->fields
        );
    }

    /**
     * @test
     */
    public function itMergesUniqueNodeListsWithOverwrite(): void
    {
        $objectType1 = PartialParser::objectTypeDefinition('
        type User {
            first_name: String
            email: String
        }
        ');

        $objectType2 = PartialParser::objectTypeDefinition('
        type User {
            first_name: String @foo
            last_name: String
        }
        ');

        $objectType1->fields = ASTHelper::mergeUniqueNodeList(
            $objectType1->fields,
            $objectType2->fields,
            true
        );

        $this->assertCount(3, $objectType1->fields);

        $firstNameField = (new Collection($objectType1->fields))->first(function ($field): bool {
            return $field->name->value === 'first_name';
        });

        $this->assertCount(1, $firstNameField->directives);
    }

    /**
     * @test
     */
    public function itCanExtractStringArguments(): void
    {
        $directive = PartialParser::directive('@foo(bar: "baz")');
        $this->assertSame(
            'baz',
            ASTHelper::directiveArgValue($directive, 'bar')
        );
    }

    /**
     * @test
     */
    public function itCanExtractBooleanArguments(): void
    {
        $directive = PartialParser::directive('@foo(bar: true)');
        $this->assertTrue(
            ASTHelper::directiveArgValue($directive, 'bar')
        );
    }

    /**
     * @test
     */
    public function itCanExtractArrayArguments(): void
    {
        $directive = PartialParser::directive('@foo(bar: ["one", "two"])');
        $this->assertSame(
            ['one', 'two'],
            ASTHelper::directiveArgValue($directive, 'bar')
        );
    }

    /**
     * @test
     */
    public function itCanExtractObjectArguments(): void
    {
        $directive = PartialParser::directive('@foo(bar: { baz: "foobar" })');
        $this->assertSame(
            ['baz' => 'foobar'],
            ASTHelper::directiveArgValue($directive, 'bar')
        );
    }

    /**
     * @test
     */
    public function itReturnsNullForNonExistingArgumentOnDirective(): void
    {
        $directive = PartialParser::directive('@foo');
        $this->assertNull(
            ASTHelper::directiveArgValue($directive, 'bar')
        );
    }
}
