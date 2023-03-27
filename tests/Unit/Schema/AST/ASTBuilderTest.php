<?php declare(strict_types=1);

namespace Tests\Unit\Schema\AST;

use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;
use Nuwave\Lighthouse\Support\Utils;
use Tests\TestCase;

final class ASTBuilderTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\AST\ASTBuilder
     */
    protected $astBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->astBuilder = $this->app->make(ASTBuilder::class);
    }

    public function testMergeTypeExtensionFields(): void
    {
        $this->schema = /** @lang GraphQL */ '
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

        $queryType = $documentAST->types[RootType::QUERY];
        assert($queryType instanceof ObjectTypeDefinitionNode);

        $this->assertCount(3, $queryType->fields);
    }

    public function testAllowsExtendingUndefinedRootTypes(): void
    {
        $this->schema = /** @lang GraphQL */ '
        extend type Query {
            foo: ID
        }

        extend type Mutation {
            bar: ID
        }

        extend type Subscription {
            baz: ID
        }
        ';
        $documentAST = $this->astBuilder->documentAST();

        $queryType = $documentAST->types[RootType::QUERY];
        assert($queryType instanceof ObjectTypeDefinitionNode);

        $this->assertCount(1, $queryType->fields);

        $mutationType = $documentAST->types[RootType::MUTATION];
        assert($mutationType instanceof ObjectTypeDefinitionNode);

        $this->assertCount(1, $mutationType->fields);

        $subscriptionType = $documentAST->types[RootType::SUBSCRIPTION];
        assert($subscriptionType instanceof ObjectTypeDefinitionNode);

        $this->assertCount(1, $subscriptionType->fields);
    }

    public function testMergeInputExtensionFields(): void
    {
        $this->schema = /** @lang GraphQL */ '
        input Inputs {
            foo: String
        }

        extend input Inputs {
            bar: Int!
        }

        extend input Inputs {
            baz: Boolean
        }
        ';
        $documentAST = $this->astBuilder->documentAST();

        $inputs = $documentAST->types['Inputs'];
        assert($inputs instanceof InputObjectTypeDefinitionNode);

        $this->assertCount(3, $inputs->fields);
    }

    public function testMergeInterfaceExtensionFields(): void
    {
        $this->schema = /** @lang GraphQL */ '
        interface Named {
          name: String!
        }

        extend interface Named {
          bar: Int!
        }

        extend interface Named {
          baz: Boolean
        }
        ';
        $documentAST = $this->astBuilder->documentAST();

        $named = $documentAST->types['Named'];
        assert($named instanceof InterfaceTypeDefinitionNode);

        $this->assertCount(3, $named->fields);
    }

    public function testMergeEnumExtensionFields(): void
    {
        $this->schema = /** @lang GraphQL */ '
        enum MyEnum {
            ONE
            TWO
        }

        extend enum MyEnum {
            THREE
        }

        extend enum MyEnum {
            FOUR
        }
        ';
        $documentAST = $this->astBuilder->documentAST();

        $myEnum = $documentAST->types['MyEnum'];
        assert($myEnum instanceof EnumTypeDefinitionNode);

        $this->assertCount(4, $myEnum->values);
    }

    public function testDoesNotAllowExtendingUndefinedTypes(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: String
        }

        extend type Foo {
            foo: Int
        }
        ';

        $this->expectExceptionObject(new DefinitionException('Could not find a base definition Foo of kind ' . NodeKind::OBJECT_TYPE_EXTENSION . ' to extend.'));
        $this->astBuilder->documentAST();
    }

    public function testDoesNotAllowDuplicateFieldsOnTypeExtensions(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: String
        }

        extend type Query {
            foo: Int
        }
        ';

        $this->expectExceptionObject(new DefinitionException(ASTHelper::duplicateDefinition('foo')));
        $this->astBuilder->documentAST();
    }

    public function testDoesNotAllowDuplicateFieldsOnInputExtensions(): void
    {
        $this->schema = /** @lang GraphQL */ '
        input Inputs {
            foo: String
        }

        extend input Inputs {
            foo: Int
        }
        ';

        $this->expectExceptionObject(new DefinitionException(ASTHelper::duplicateDefinition('foo')));
        $this->astBuilder->documentAST();
    }

    public function testDoesNotAllowDuplicateFieldsOnInterfaceExtensions(): void
    {
        $this->schema = /** @lang GraphQL */ '
        interface Named {
            foo: String
        }

        extend interface Named{
            foo: Int
        }
        ';

        $this->expectExceptionObject(new DefinitionException(ASTHelper::duplicateDefinition('foo')));
        $this->astBuilder->documentAST();
    }

    public function testDoesNotAllowDuplicateValuesOnEnumExtensions(): void
    {
        $this->schema = /** @lang GraphQL */ '
        enum MyEnum {
            ONE
            TWO
        }

        extend enum MyEnum {
            TWO
            THREE
        }
        ';

        $this->expectExceptionObject(new DefinitionException(ASTHelper::duplicateDefinition('TWO')));
        $this->astBuilder->documentAST();
    }

    public function testDoesNotAllowMergingNonMatchingTypes(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Foo {
            bar: ID
        }

        extend interface Foo {
            baz: ID
        }
        ';

        $this->expectExceptionObject(new DefinitionException('The type extension Foo of kind ' . NodeKind::INTERFACE_TYPE_EXTENSION . ' can not extend a definition of kind ' . NodeKind::OBJECT_TYPE_DEFINITION . '.'));
        $this->astBuilder->documentAST();
    }

    public function testMergeTypeExtensionInterfaces(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User implements Emailable {
            email: String!
        }

        interface Emailable {
            email: String!
        }

        interface Nameable {
            name: String!
        }

        extend type User implements Nameable {
            name: String!
        }
        ';
        $documentAST = $this->astBuilder->documentAST();

        $userType = $documentAST->types['User'];
        assert($userType instanceof ObjectTypeDefinitionNode);

        $interfaces = new Collection($userType->interfaces);
        $this->assertCount(2, $interfaces);
        $this->assertTrue($interfaces->contains('name.value', 'Emailable'));
        $this->assertTrue($interfaces->contains('name.value', 'Nameable'));
    }

    public function testCompositeTypeDefinitionManipulatorDirective(): void
    {
        $directive = new class() extends BaseDirective implements TypeManipulator {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @foo on FIELD_DEFINITION';
            }

            public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition):void {
                $typeDefinition->fields[0]->type = Parser::namedType('Int');
            }
        };
    
        Utils::accessProtected($this->astBuilder, 'directiveLocator')->setResolved('foo', $directive::class);

        $compositeDirective = new class() implements TypeManipulator {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @composite on FIELD_DEFINITION';
            }

            public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition):void {
                $typeDefinition->directives[] = Parser::directive('@foo');
            }
        };

        Utils::accessProtected($this->astBuilder, 'directiveLocator')->setResolved('composite', $compositeDirective::class);

        $this->schema = /** @lang GraphQL */ '
        type Foo @composite {
            bar: String
        }
        ';
        $documentAST = $this->astBuilder->documentAST();

        $this->assertEquals($documentAST->types['Foo']->fields[0]->type->name->value, 'Int');
    }

    public function testCompositeFieldManipulatorDirective(): void
    {
        $directive = new class() extends BaseDirective implements FieldManipulator {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @foo on FIELD_DEFINITION';
            }

            public function manipulateFieldDefinition(
                DocumentAST &$documentAST,
                FieldDefinitionNode &$fieldDefinition,
                ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
            ):void {
                $fieldDefinition->type = Parser::namedType('Int');
            }
        };
    
        Utils::accessProtected($this->astBuilder, 'directiveLocator')->setResolved('foo', $directive::class);

        $compositeDirective = new class() implements FieldManipulator {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @composite on FIELD_DEFINITION';
            }

            public function manipulateFieldDefinition(
                DocumentAST &$documentAST,
                FieldDefinitionNode &$fieldDefinition,
                ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
            ):void {
                $fieldDefinition->directives[] = Parser::directive('@foo');
            }
        };

        Utils::accessProtected($this->astBuilder, 'directiveLocator')->setResolved('composite', $compositeDirective::class);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: String @composite
        }
        ';
        $documentAST = $this->astBuilder->documentAST();

        $this->assertEquals($documentAST->types[RootType::QUERY]->fields[0]->type->name->value, 'Int');
    }

    public function testCompositeArgManipulatorDirective(): void
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
            ): void
            {
                $argDefinition->type = Parser::namedType('Int');
            }
        };
    
        Utils::accessProtected($this->astBuilder, 'directiveLocator')->setResolved('foo', $directive::class);

        $compositeDirective = new class() implements ArgManipulator {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @composite on ARGUMENT_DEFINITION';
            }

            public function manipulateArgDefinition(
                DocumentAST &$documentAST,
                InputValueDefinitionNode &$argDefinition,
                FieldDefinitionNode &$parentField,
                ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
            ): void
            {
                $argDefinition->directives[] = Parser::directive('@foo');
            }
        };

        Utils::accessProtected($this->astBuilder, 'directiveLocator')->setResolved('composite', $compositeDirective::class);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo( name: String @composite ): String
        }
        ';
        $documentAST = $this->astBuilder->documentAST();

        $this->assertEquals($documentAST->types[RootType::QUERY]->fields[0]->arguments[0]->type->name->value, 'Int');
    }
}
