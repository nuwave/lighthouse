<?php declare(strict_types=1);

namespace Tests\Unit\Schema\AST;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\InputFieldManipulator;
use Tests\TestCase;

final class ASTHelperTest extends TestCase
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
        ASTHelper::mergeUniqueNodeList(
            $objectType1->fields,
            $objectType2->fields,
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
            true,
        );

        $this->assertCount(3, $objectType1->fields);

        $firstNameField = ASTHelper::firstByName($objectType1->fields, 'first_name');

        assert($firstNameField instanceof FieldDefinitionNode);
        $this->assertCount(1, $firstNameField->directives);
    }

    public function testExtractStringArguments(): void
    {
        $directive = Parser::constDirective(/** @lang GraphQL */ '@foo(bar: "baz")');
        $this->assertSame(
            'baz',
            ASTHelper::directiveArgValue($directive, 'bar'),
        );
    }

    public function testExtractBooleanArguments(): void
    {
        $directive = Parser::constDirective(/** @lang GraphQL */ '@foo(bar: true)');
        $this->assertTrue(
            ASTHelper::directiveArgValue($directive, 'bar'),
        );
    }

    public function testExtractArrayArguments(): void
    {
        $directive = Parser::constDirective(/** @lang GraphQL */ '@foo(bar: ["one", "two"])');
        $this->assertSame(
            ['one', 'two'],
            ASTHelper::directiveArgValue($directive, 'bar'),
        );
    }

    public function testExtractObjectArguments(): void
    {
        $directive = Parser::constDirective(/** @lang GraphQL */ '@foo(bar: { baz: "foobar" })');
        $this->assertSame(
            ['baz' => 'foobar'],
            ASTHelper::directiveArgValue($directive, 'bar'),
        );
    }

    public function testReturnsNullForNonExistingArgumentOnDirective(): void
    {
        $directive = Parser::constDirective(/** @lang GraphQL */ '@foo');
        $this->assertNull(
            ASTHelper::directiveArgValue($directive, 'bar'),
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

    public function testAddDirectiveToFields(): void
    {
        $object = Parser::objectTypeDefinition(/** @lang GraphQL */ '
        type Query {
            foo: Int
        }
        ');

        ASTHelper::addDirectiveToFields(
            Parser::constDirective(/** @lang GraphQL */ '@guard'),
            $object,
        );

        $this->assertSame(
            'guard',
            $object->fields[0]->directives[0]->name->value,
        );
    }

    public function testPrefersFieldDirectivesOverTypeDirectives(): void
    {
        $object = Parser::objectTypeDefinition(/** @lang GraphQL */ '
        type Query {
            foo: Int @complexity(resolver: "Foo")
            bar: String
        }
        ');

        ASTHelper::addDirectiveToFields(
            Parser::constDirective(/** @lang GraphQL */ '@complexity'),
            $object,
        );

        $onBar = $object->fields[1]->directives[0];
        $this->assertSame('complexity', $onBar->name->value);

        $onFoo = $object->fields[0]->directives[0];
        $this->assertSame('Foo', ASTHelper::directiveArgValue($onFoo, 'resolver'));
    }

    public function testExtractDirectiveDefinition(): void
    {
        $directive = ASTHelper::extractDirectiveDefinition(/** @lang GraphQL */ 'directive @foo on OBJECT');
        $this->assertSame('foo', $directive->name->value);
    }

    public function testExtractDirectiveDefinitionAllowsAuxiliaryTypes(): void
    {
        $directive = ASTHelper::extractDirectiveDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
directive @foo on OBJECT
scalar Bar
GRAPHQL
        );
        $this->assertSame('foo', $directive->name->value);
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

    public function testUnderlyingTypeKnowsStandardTypes(): void
    {
        $type = ASTHelper::underlyingType(
            Parser::fieldDefinition('foo: ID'),
        );

        $this->assertInstanceOf(ScalarTypeDefinitionNode::class, $type);
        $this->assertSame(Type::ID, $type->name->value);
    }

    public function testUnderlyingTypeReturnsNullOnUnknownType(): void
    {
        $type = ASTHelper::underlyingType(
            Parser::fieldDefinition('foo: Unknown'),
        );
        $this->assertNull($type);
    }

    public function testModelNameGuessesProgrammaticallyRegisteredTypeName(): void
    {
        $modelName = ASTHelper::modelName(
            Parser::fieldDefinition('foo: ProgrammaticallyRegistered'),
        );
        $this->assertSame('ProgrammaticallyRegistered', $modelName);
    }

    public function testDynamicallyAddedFieldManipulatorDirective(): void
    {
        $astBuilder = $this->app->make(ASTBuilder::class);

        $directive = new class() extends BaseDirective implements FieldManipulator {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @foo on FIELD_DEFINITION';
            }

            public function manipulateFieldDefinition(
                DocumentAST &$documentAST,
                FieldDefinitionNode &$fieldDefinition,
                ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
            ): void {
                $fieldDefinition->type = Parser::namedType('Int');
            }
        };

        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('foo', $directive::class);

        $dynamicDirective = new class() extends BaseDirective implements FieldManipulator {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @dynamic on FIELD_DEFINITION';
            }

            public function manipulateFieldDefinition(
                DocumentAST &$documentAST,
                FieldDefinitionNode &$fieldDefinition,
                ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
            ): void {
                $directiveInstance = ASTHelper::addDirectiveToNode('@foo', $fieldDefinition);
                assert($directiveInstance instanceof FieldManipulator);

                $directiveInstance->manipulateFieldDefinition($documentAST, $fieldDefinition, $parentType);
            }
        };

        $directiveLocator->setResolved('dynamic', $dynamicDirective::class);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: String @dynamic
        }
        ';
        $documentAST = $astBuilder->documentAST();

        $queryType = $documentAST->types[RootType::QUERY];
        assert($queryType instanceof ObjectTypeDefinitionNode);

        $fieldType = $queryType->fields[0];
        assert($fieldType instanceof FieldDefinitionNode);

        $typeType = $fieldType->type;
        assert($typeType instanceof NamedTypeNode);

        $this->assertSame('Int', $typeType->name->value);
    }

    public function testDynamicallyAddedArgManipulatorDirective(): void
    {
        $directive = new class() extends BaseDirective implements ArgManipulator {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @foo on ARGUMENT_DEFINITION';
            }

            public function manipulateArgDefinition(
                DocumentAST &$documentAST,
                InputValueDefinitionNode &$argDefinition,
                FieldDefinitionNode &$parentField,
                ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
            ): void {
                $argDefinition->type = Parser::namedType('Int');
            }
        };

        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('foo', $directive::class);

        $dynamicDirective = new class() extends BaseDirective implements ArgManipulator {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @dynamic on ARGUMENT_DEFINITION';
            }

            public function manipulateArgDefinition(
                DocumentAST &$documentAST,
                InputValueDefinitionNode &$argDefinition,
                FieldDefinitionNode &$parentField,
                ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
            ): void {
                $directiveInstance = ASTHelper::addDirectiveToNode('@foo', $argDefinition);
                assert($directiveInstance instanceof ArgManipulator);

                $directiveInstance->manipulateArgDefinition($documentAST, $argDefinition, $parentField, $parentType);
            }
        };

        $directiveLocator->setResolved('dynamic', $dynamicDirective::class);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(name: String @dynamic): String
        }
        ';
        $astBuilder = $this->app->make(ASTBuilder::class);
        $documentAST = $astBuilder->documentAST();

        $queryType = $documentAST->types[RootType::QUERY];
        assert($queryType instanceof ObjectTypeDefinitionNode);

        $fieldType = $queryType->fields[0];
        assert($fieldType instanceof FieldDefinitionNode);

        $argumentType = $fieldType->arguments[0];
        assert($argumentType instanceof InputValueDefinitionNode);

        $typeType = $argumentType->type;
        assert($typeType instanceof NamedTypeNode);

        $this->assertSame('Int', $typeType->name->value);
    }

    public function testDynamicallyAddedInputFieldManipulatorDirective(): void
    {
        $directive = new class() extends BaseDirective implements InputFieldManipulator {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @foo on INPUT_FIELD_DEFINITION';
            }

            public function manipulateInputFieldDefinition(
                DocumentAST &$documentAST,
                InputValueDefinitionNode &$inputField,
                InputObjectTypeDefinitionNode &$parentInput,
            ): void {
                $inputField->type = Parser::namedType('Int');
            }
        };

        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('foo', $directive::class);

        $dynamicDirective = new class() extends BaseDirective implements InputFieldManipulator {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @dynamic on INPUT_FIELD_DEFINITION';
            }

            public function manipulateInputFieldDefinition(
                DocumentAST &$documentAST,
                InputValueDefinitionNode &$inputField,
                InputObjectTypeDefinitionNode &$parentInput,
            ): void {
                $directiveInstance = ASTHelper::addDirectiveToNode('@foo', $inputField);
                assert($directiveInstance instanceof InputFieldManipulator);

                $directiveInstance->manipulateInputFieldDefinition($documentAST, $inputField, $parentInput);
            }
        };

        $directiveLocator->setResolved('dynamic', $dynamicDirective::class);

        $this->schema = /** @lang GraphQL */ '
        input Input {
            name: String @dynamic
        }

        type Query {
            foo(name: Input): String
        }
        ';
        $astBuilder = $this->app->make(ASTBuilder::class);
        $documentAST = $astBuilder->documentAST();

        $inputType = $documentAST->types['Input'];
        assert($inputType instanceof InputObjectTypeDefinitionNode);

        $fieldType = $inputType->fields[0];
        assert($fieldType instanceof InputValueDefinitionNode);

        $typeType = $fieldType->type;
        assert($typeType instanceof NamedTypeNode);

        $this->assertSame('Int', $typeType->name->value);
    }
}
