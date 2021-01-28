<?php

namespace Tests\Unit\Schema\AST;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Tests\TestCase;

class ASTHelperTest extends TestCase
{
    public function testThrowsWhenMergingUniqueNodeListWithCollision(): void
    {
        $objectType1 = Parser::objectTypeDefinition(/** @lang GraphQL */ '
        type User {
            email: String
        }
        ');

        $objectType2 = Parser::objectTypeDefinition(/** @lang GraphQL */ '
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
        $objectType1 = Parser::objectTypeDefinition(/** @lang GraphQL */ '
        type User {
            first_name: String
            email: String
        }
        ');

        $objectType2 = Parser::objectTypeDefinition(/** @lang GraphQL */ '
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

        /** @var \GraphQL\Language\AST\FieldDefinitionNode $firstNameField */
        $firstNameField = ASTHelper::firstByName($objectType1->fields, 'first_name');

        $this->assertCount(1, $firstNameField->directives);
    }

    public function testCanExtractStringArguments(): void
    {
        $directive = Parser::constDirective(/** @lang GraphQL */ '@foo(bar: "baz")');
        $this->assertSame(
            'baz',
            ASTHelper::directiveArgValue($directive, 'bar')
        );
    }

    public function testCanExtractBooleanArguments(): void
    {
        $directive = Parser::constDirective(/** @lang GraphQL */ '@foo(bar: true)');
        $this->assertTrue(
            ASTHelper::directiveArgValue($directive, 'bar')
        );
    }

    public function testCanExtractArrayArguments(): void
    {
        $directive = Parser::constDirective(/** @lang GraphQL */ '@foo(bar: ["one", "two"])');
        $this->assertSame(
            ['one', 'two'],
            ASTHelper::directiveArgValue($directive, 'bar')
        );
    }

    public function testCanExtractObjectArguments(): void
    {
        $directive = Parser::constDirective(/** @lang GraphQL */ '@foo(bar: { baz: "foobar" })');
        $this->assertSame(
            ['baz' => 'foobar'],
            ASTHelper::directiveArgValue($directive, 'bar')
        );
    }

    public function testReturnsNullForNonExistingArgumentOnDirective(): void
    {
        $directive = Parser::constDirective(/** @lang GraphQL */ '@foo');
        $this->assertNull(
            ASTHelper::directiveArgValue($directive, 'bar')
        );
    }

    public function testChecksWhetherTypeImplementsInterface(): void
    {
        $type = Parser::objectTypeDefinition(/** @lang GraphQL */ '
        type Foo implements Bar {
            baz: String
        }
        ');
        $this->assertTrue(ASTHelper::typeImplementsInterface($type, 'Bar'));
        $this->assertFalse(ASTHelper::typeImplementsInterface($type, 'FakeInterface'));
    }

    public function testThrowsWhenDefinedOnInvalidTypes(): void
    {
        $notAnObject = Parser::scalarTypeDefinition(/** @lang GraphQL */ '
        scalar NotAnObject
        ');
        $directive = Parser::constDirective(/** @lang GraphQL */ '@foo');

        $this->expectException(DefinitionException::class);
        ASTHelper::addDirectiveToFields($directive, $notAnObject);
    }

    public function testAddDirectiveToFields(): void
    {
        $object = Parser::objectTypeDefinition(/** @lang GraphQL */ '
        type Query {
            foo: Int
        }
        ');

        ASTHelper::addDirectiveToFields(
            Parser::constDirective(/** @lang GraphQL */ '@guard'),
            $object
        );

        $this->assertSame(
            'guard',
            $object->fields[0]->directives[0]->name->value
        );
    }

    public function testPrefersFieldDirectivesOverTypeDirectives(): void
    {
        $object = Parser::objectTypeDefinition(/** @lang GraphQL */ '
        type Query {
            foo: Int @guard(with: ["api"])
            bar: String
        }
        ');

        ASTHelper::addDirectiveToFields(
            Parser::constDirective(/** @lang GraphQL */ '@guard'),
            $object
        );

        $guardOnFooArguments = $object->fields[0]->directives[0];
        $fieldGuard = ASTHelper::directiveArgValue($guardOnFooArguments, 'with');

        $this->assertSame(['api'], $fieldGuard);
        $this->assertSame(
            'guard',
            $object->fields[1]->directives[0]->name->value
        );
    }

    public function testExtractDirectiveDefinition(): void
    {
        $this->assertInstanceOf(
            DirectiveDefinitionNode::class,
            ASTHelper::extractDirectiveDefinition(/** @lang GraphQL */ 'directive @foo on OBJECT')
        );
    }

    public function testExtractDirectiveDefinitionAllowsAuxiliaryTypes(): void
    {
        $this->assertInstanceOf(
            DirectiveDefinitionNode::class,
            ASTHelper::extractDirectiveDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
directive @foo on OBJECT
scalar Bar
GRAPHQL
)
        );
    }

    public function testThrowsOnSyntaxError(): void
    {
        $this->expectException(DefinitionException::class);

        ASTHelper::extractDirectiveDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
invalid GraphQL
GRAPHQL
        );
    }

    public function testThrowsIfMissingDirectiveDefinitions(): void
    {
        $this->expectException(DefinitionException::class);

        ASTHelper::extractDirectiveDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
scalar Foo
GRAPHQL
        );
    }

    public function testThrowsOnMultipleDirectiveDefinitions(): void
    {
        $this->expectException(DefinitionException::class);

        ASTHelper::extractDirectiveDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
directive @foo on OBJECT
directive @bar on OBJECT
GRAPHQL
        );
    }
}
