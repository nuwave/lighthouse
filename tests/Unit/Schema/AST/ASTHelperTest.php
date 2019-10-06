<?php

namespace Tests\Unit\Schema\AST;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

class ASTHelperTest extends TestCase
{
    public function testThrowsWhenMergingUniqueNodeListWithCollision(): void
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

    public function testMergesUniqueNodeListsWithOverwrite(): void
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

        $firstNameField = ASTHelper::firstByName($objectType1->fields, 'first_name');

        $this->assertCount(1, $firstNameField->directives);
    }

    public function testCanExtractStringArguments(): void
    {
        $directive = PartialParser::directive('@foo(bar: "baz")');
        $this->assertSame(
            'baz',
            ASTHelper::directiveArgValue($directive, 'bar')
        );
    }

    public function testCanExtractBooleanArguments(): void
    {
        $directive = PartialParser::directive('@foo(bar: true)');
        $this->assertTrue(
            ASTHelper::directiveArgValue($directive, 'bar')
        );
    }

    public function testCanExtractArrayArguments(): void
    {
        $directive = PartialParser::directive('@foo(bar: ["one", "two"])');
        $this->assertSame(
            ['one', 'two'],
            ASTHelper::directiveArgValue($directive, 'bar')
        );
    }

    public function testCanExtractObjectArguments(): void
    {
        $directive = PartialParser::directive('@foo(bar: { baz: "foobar" })');
        $this->assertSame(
            ['baz' => 'foobar'],
            ASTHelper::directiveArgValue($directive, 'bar')
        );
    }

    public function testReturnsNullForNonExistingArgumentOnDirective(): void
    {
        $directive = PartialParser::directive('@foo');
        $this->assertNull(
            ASTHelper::directiveArgValue($directive, 'bar')
        );
    }

    public function testChecksWhetherTypeImplementsInterface(): void
    {
        $type = PartialParser::objectTypeDefinition('
            type Foo implements Bar {
                baz: String
            }
        ');
        $this->assertTrue(ASTHelper::typeImplementsInterface($type, 'Bar'));
        $this->assertFalse(ASTHelper::typeImplementsInterface($type, 'FakeInterface'));
    }
}
