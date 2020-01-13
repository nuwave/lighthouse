<?php

namespace Tests\Unit\Schema\AST;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Tests\TestCase;

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

    public function testThrowsWhenDefinedOnInvalidTypes(): void
    {
        $notAnObject = PartialParser::scalarTypeDefinition(/** @lang GraphQL */ '
        scalar NotAnObject
        ');
        $directive = PartialParser::directive(/** @lang GraphQL */ '@foo');

        $this->expectException(DefinitionException::class);
        ASTHelper::addDirectiveToFields($directive, $notAnObject);
    }

    public function testAddDirectiveToFields(): void
    {
        $object = PartialParser::objectTypeDefinition(/** @lang GraphQL */ '
        type Query {
            foo: Int
        }
        ');

        ASTHelper::addDirectiveToFields(
            PartialParser::directive(/** @lang GraphQL */ '@guard'),
            $object
        );

        $this->assertSame(
            'guard',
            $object->fields[0]->directives[0]->name->value
        );
    }

    public function testPrefersFieldDirectivesOverTypeDirectives(): void
    {
        $object = PartialParser::objectTypeDefinition(/** @lang GraphQL */ '
        type Query {
            foo: Int @guard(with: "api")
            bar: String
        }
        ');

        ASTHelper::addDirectiveToFields(
            PartialParser::directive(/** @lang GraphQL */ '@guard'),
            $object
        );

        $guardOnFooArguments = $object->fields[0]->directives[0];
        $fieldGuard = ASTHelper::directiveArgValue($guardOnFooArguments, 'with');

        $this->assertSame('api', $fieldGuard);
        $this->assertSame(
            'guard',
            $object->fields[1]->directives[0]->name->value
        );
    }
}
