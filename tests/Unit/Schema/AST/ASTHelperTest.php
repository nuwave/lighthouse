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
        $objectType1 = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
                type User {
                    email: String
                }
        GRAPHQL);

        $objectType2 = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
                type User {
                    email(bar: String): Int
                }
        GRAPHQL);

        $this->expectException(DefinitionException::class);
        ASTHelper::mergeUniqueNodeList(
            $objectType1->fields,
            $objectType2->fields,
        );
    }

    public function testMergesUniqueNodeListsWithOverwrite(): void
    {
        $objectType1 = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
                type User {
                    first_name: String
                    email: String
                }
        GRAPHQL);

        $objectType2 = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
                type User {
                    first_name: String @foo
                    last_name: String
                }
        GRAPHQL);

        $objectType1->fields = ASTHelper::mergeUniqueNodeList(
            $objectType1->fields,
            $objectType2->fields,
            true,
        );

        $this->assertCount(3, $objectType1->fields);

        $firstNameField = ASTHelper::firstByName($objectType1->fields, 'first_name');

        $this->assertInstanceOf(FieldDefinitionNode::class, $firstNameField);
        $this->assertCount(1, $firstNameField->directives);
    }

    public function testExtractStringArguments(): void
    {
        $directive = Parser::constDirective(/** @lang GraphQL */ <<<'GRAPHQL'
        @foo(bar: "baz")
        GRAPHQL);
        $this->assertSame(
            'baz',
            ASTHelper::directiveArgValue($directive, 'bar'),
        );
    }

    public function testExtractBooleanArguments(): void
    {
        $directive = Parser::constDirective(/** @lang GraphQL */ <<<'GRAPHQL'
        @foo(bar: true)
        GRAPHQL);
        $this->assertTrue(
            ASTHelper::directiveArgValue($directive, 'bar'),
        );
    }

    public function testExtractArrayArguments(): void
    {
        $directive = Parser::constDirective(/** @lang GraphQL */ <<<'GRAPHQL'
        @foo(bar: ["one", "two"])
        GRAPHQL);
        $this->assertSame(
            ['one', 'two'],
            ASTHelper::directiveArgValue($directive, 'bar'),
        );
    }

    public function testExtractObjectArguments(): void
    {
        $directive = Parser::constDirective(/** @lang GraphQL */ <<<'GRAPHQL'
        @foo(bar: { baz: "foobar" })
        GRAPHQL);
        $this->assertSame(
            ['baz' => 'foobar'],
            ASTHelper::directiveArgValue($directive, 'bar'),
        );
    }

    public function testReturnsNullForNonExistingArgumentOnDirective(): void
    {
        $directive = Parser::constDirective(/** @lang GraphQL */ <<<'GRAPHQL'
        @foo
        GRAPHQL);
        $this->assertNull(
            ASTHelper::directiveArgValue($directive, 'bar'),
        );
    }

    public function testChecksWhetherTypeImplementsInterface(): void
    {
        $type = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
                type Foo implements Bar {
                    baz: String
                }
        GRAPHQL);
        $this->assertTrue(ASTHelper::typeImplementsInterface($type, 'Bar'));
        $this->assertFalse(ASTHelper::typeImplementsInterface($type, 'FakeInterface'));
    }

    public function testAddDirectiveToFields(): void
    {
        $object = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo: Int
                }
        GRAPHQL);

        ASTHelper::addDirectiveToFields(
            Parser::constDirective(/** @lang GraphQL */ <<<'GRAPHQL'
            @guard
            GRAPHQL),
            $object,
        );

        $this->assertSame(
            'guard',
            $object->fields[0]->directives[0]->name->value,
        );
    }

    public function testPrefersFieldDirectivesOverTypeDirectives(): void
    {
        $object = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo: Int @complexity(resolver: "Foo")
                    bar: String
                }
        GRAPHQL);

        ASTHelper::addDirectiveToFields(
            Parser::constDirective(/** @lang GraphQL */ <<<'GRAPHQL'
            @complexity
            GRAPHQL),
            $object,
        );

        $onBar = $object->fields[1]->directives[0];
        $this->assertSame('complexity', $onBar->name->value);

        $onFoo = $object->fields[0]->directives[0];
        $this->assertSame('Foo', ASTHelper::directiveArgValue($onFoo, 'resolver'));
    }

    public function testExtractDirectiveDefinition(): void
    {
        $directive = ASTHelper::extractDirectiveDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
        directive @foo on OBJECT
        GRAPHQL);
        $this->assertSame('foo', $directive->name->value);
    }

    public function testExtractDirectiveDefinitionAllowsAuxiliaryTypes(): void
    {
        $directive = ASTHelper::extractDirectiveDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
                directive @foo on OBJECT
                scalar Bar
        GRAPHQL);
        $this->assertSame('foo', $directive->name->value);
    }

    public function testThrowsOnSyntaxError(): void
    {
        $this->expectException(DefinitionException::class);
        ASTHelper::extractDirectiveDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
                invalid GraphQL
        GRAPHQL);
    }

    public function testThrowsIfMissingDirectiveDefinitions(): void
    {
        $this->expectException(DefinitionException::class);
        ASTHelper::extractDirectiveDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
                scalar Foo
        GRAPHQL);
    }

    public function testThrowsOnMultipleDirectiveDefinitions(): void
    {
        $this->expectException(DefinitionException::class);
        ASTHelper::extractDirectiveDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
                directive @foo on OBJECT
                directive @bar on OBJECT
        GRAPHQL);
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
                return /** @lang GraphQL */ <<<'GRAPHQL'
                directive @foo on FIELD_DEFINITION
                GRAPHQL;
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
                return /** @lang GraphQL */ <<<'GRAPHQL'
                directive @dynamic on FIELD_DEFINITION
                GRAPHQL;
            }

            public function manipulateFieldDefinition(
                DocumentAST &$documentAST,
                FieldDefinitionNode &$fieldDefinition,
                ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
            ): void {
                $directiveInstance = ASTHelper::addDirectiveToNode('@foo', $fieldDefinition);
                TestCase::assertInstanceOf(FieldManipulator::class, $directiveInstance);

                $directiveInstance->manipulateFieldDefinition($documentAST, $fieldDefinition, $parentType);
            }
        };

        $directiveLocator->setResolved('dynamic', $dynamicDirective::class);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo: String @dynamic
                }
        GRAPHQL;
        $documentAST = $astBuilder->documentAST();

        $queryType = $documentAST->types[RootType::QUERY];
        $this->assertInstanceOf(ObjectTypeDefinitionNode::class, $queryType);

        $fieldType = $queryType->fields[0];
        $this->assertInstanceOf(FieldDefinitionNode::class, $fieldType); // @phpstan-ignore method.alreadyNarrowedType (aids IDE)

        $typeType = $fieldType->type;
        $this->assertInstanceOf(NamedTypeNode::class, $typeType);

        $this->assertSame('Int', $typeType->name->value);
    }

    public function testDynamicallyAddedArgManipulatorDirective(): void
    {
        $directive = new class() extends BaseDirective implements ArgManipulator {
            public static function definition(): string
            {
                return /** @lang GraphQL */ <<<'GRAPHQL'
                directive @foo on ARGUMENT_DEFINITION
                GRAPHQL;
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
                return /** @lang GraphQL */ <<<'GRAPHQL'
                directive @dynamic on ARGUMENT_DEFINITION
                GRAPHQL;
            }

            public function manipulateArgDefinition(
                DocumentAST &$documentAST,
                InputValueDefinitionNode &$argDefinition,
                FieldDefinitionNode &$parentField,
                ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
            ): void {
                $directiveInstance = ASTHelper::addDirectiveToNode('@foo', $argDefinition);
                TestCase::assertInstanceOf(ArgManipulator::class, $directiveInstance);

                $directiveInstance->manipulateArgDefinition($documentAST, $argDefinition, $parentField, $parentType);
            }
        };

        $directiveLocator->setResolved('dynamic', $dynamicDirective::class);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo(name: String @dynamic): String
                }
        GRAPHQL;
        $astBuilder = $this->app->make(ASTBuilder::class);
        $documentAST = $astBuilder->documentAST();

        $queryType = $documentAST->types[RootType::QUERY];
        $this->assertInstanceOf(ObjectTypeDefinitionNode::class, $queryType);

        $fieldType = $queryType->fields[0];
        $this->assertInstanceOf(FieldDefinitionNode::class, $fieldType); // @phpstan-ignore method.alreadyNarrowedType (aids IDE)

        $argumentType = $fieldType->arguments[0];
        $this->assertInstanceOf(InputValueDefinitionNode::class, $argumentType); // @phpstan-ignore method.alreadyNarrowedType (aids IDE)

        $typeType = $argumentType->type;
        $this->assertInstanceOf(NamedTypeNode::class, $typeType);

        $this->assertSame('Int', $typeType->name->value);
    }

    public function testDynamicallyAddedInputFieldManipulatorDirective(): void
    {
        $directive = new class() extends BaseDirective implements InputFieldManipulator {
            public static function definition(): string
            {
                return /** @lang GraphQL */ <<<'GRAPHQL'
                directive @foo on INPUT_FIELD_DEFINITION
                GRAPHQL;
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
                return /** @lang GraphQL */ <<<'GRAPHQL'
                directive @dynamic on INPUT_FIELD_DEFINITION
                GRAPHQL;
            }

            public function manipulateInputFieldDefinition(
                DocumentAST &$documentAST,
                InputValueDefinitionNode &$inputField,
                InputObjectTypeDefinitionNode &$parentInput,
            ): void {
                $directiveInstance = ASTHelper::addDirectiveToNode('@foo', $inputField);
                TestCase::assertInstanceOf(InputFieldManipulator::class, $directiveInstance);

                $directiveInstance->manipulateInputFieldDefinition($documentAST, $inputField, $parentInput);
            }
        };

        $directiveLocator->setResolved('dynamic', $dynamicDirective::class);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                input Input {
                    name: String @dynamic
                }
        
                type Query {
                    foo(name: Input): String
                }
        GRAPHQL;
        $astBuilder = $this->app->make(ASTBuilder::class);
        $documentAST = $astBuilder->documentAST();

        $inputType = $documentAST->types['Input'];
        $this->assertInstanceOf(InputObjectTypeDefinitionNode::class, $inputType);

        $fieldType = $inputType->fields[0];
        $this->assertInstanceOf(InputValueDefinitionNode::class, $fieldType); // @phpstan-ignore method.alreadyNarrowedType (aids IDE)

        $typeType = $fieldType->type;
        $this->assertInstanceOf(NamedTypeNode::class, $typeType);

        $this->assertSame('Int', $typeType->name->value);
    }
}
