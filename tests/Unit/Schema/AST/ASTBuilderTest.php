<?php declare(strict_types=1);

namespace Tests\Unit\Schema\AST;

use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\RootType;
use Tests\TestCase;

final class ASTBuilderTest extends TestCase
{
    protected ASTBuilder $astBuilder;

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

    public function testMergeTypeExtensionDirectives(): void
    {
        $directive = new class() extends BaseDirective {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @foo repeatable on OBJECT';
            }
        };

        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('foo', $directive::class);

        $this->schema = /** @lang GraphQL */ '
        type MyType {
            field: String
        }

        extend type MyType @foo

        extend type MyType @foo
        ';
        $documentAST = $this->astBuilder->documentAST();

        $myType = $documentAST->types['MyType'];
        assert($myType instanceof ObjectTypeDefinitionNode);

        $this->assertCount(2, $myType->directives);
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

    public function testMergeInputExtensionDirectives(): void
    {
        $directive = new class() extends BaseDirective {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @foo repeatable on INPUT_OBJECT';
            }
        };

        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('foo', $directive::class);

        $this->schema = /** @lang GraphQL */ '
        input MyInput {
            field: String
        }

        extend input MyInput @foo

        extend input MyInput @foo
        ';
        $documentAST = $this->astBuilder->documentAST();

        $myInput = $documentAST->types['MyInput'];
        assert($myInput instanceof InputObjectTypeDefinitionNode);

        $this->assertCount(2, $myInput->directives);
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

    public function testMergeInterfaceExtensionDirectives(): void
    {
        $directive = new class() extends BaseDirective {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @foo repeatable on INTERFACE';
            }
        };

        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('foo', $directive::class);

        $this->schema = /** @lang GraphQL */ '
        interface MyInterface {
            field: String
        }

        extend interface MyInterface @foo

        extend interface MyInterface @foo
        ';
        $documentAST = $this->astBuilder->documentAST();

        $myInterface = $documentAST->types['MyInterface'];
        assert($myInterface instanceof InterfaceTypeDefinitionNode);

        $this->assertCount(2, $myInterface->directives);
    }

    public function testMergeScalarExtensionDirectives(): void
    {
        $directive = new class() extends BaseDirective {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @foo repeatable on SCALAR';
            }
        };

        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('foo', $directive::class);

        $this->schema = /** @lang GraphQL */ '
        scalar MyScalar

        extend scalar MyScalar @foo

        extend scalar MyScalar @foo
        ';
        $documentAST = $this->astBuilder->documentAST();

        $myScalar = $documentAST->types['MyScalar'];
        assert($myScalar instanceof ScalarTypeDefinitionNode);

        $this->assertCount(2, $myScalar->directives);
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

    public function testMergeEnumExtensionDirectives(): void
    {
        $directive = new class() extends BaseDirective {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @foo repeatable on ENUM';
            }
        };

        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('foo', $directive::class);

        $this->schema = /** @lang GraphQL */ '
        enum MyEnum {
            ONE
            TWO
        }

        extend enum MyEnum @foo

        extend enum MyEnum @foo
        ';
        $documentAST = $this->astBuilder->documentAST();

        $myEnum = $documentAST->types['MyEnum'];
        assert($myEnum instanceof EnumTypeDefinitionNode);

        $this->assertCount(2, $myEnum->directives);
    }

    public function testMergeUnionExtensionFields(): void
    {
        $this->schema = /** @lang GraphQL */ '
            type Foo
            type Bar
            type Baz

            union MyUnion = Foo

            extend union MyUnion = Bar

            extend union MyUnion = Baz
        ';
        $documentAST = $this->astBuilder->documentAST();

        $myUnion = $documentAST->types['MyUnion'];
        assert($myUnion instanceof UnionTypeDefinitionNode);

        $this->assertCount(3, $myUnion->types);
    }

    public function testDoesNotAllowExtendingUndefinedScalar(): void
    {
        $directive = new class() extends BaseDirective {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @foo repeatable on SCALAR';
            }
        };

        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('foo', $directive::class);

        $this->schema = /** @lang GraphQL */ '
        extend scalar MyScalar @foo
        ';

        $this->expectExceptionObject(new DefinitionException('Could not find a base definition MyScalar of kind ' . NodeKind::SCALAR_TYPE_EXTENSION . ' to extend.'));
        $this->astBuilder->documentAST();
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

    public function testDoesNotAllowExtendingUndefinedUnions(): void
    {
        $this->schema = /** @lang GraphQL */ '
        union MyFirstEnum = String

        extend union MySecondUnion = Int
        ';

        $this->expectExceptionObject(new DefinitionException('Could not find a base definition MySecondUnion of kind ' . NodeKind::UNION_TYPE_EXTENSION . ' to extend.'));
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

    public function testDoesNotAllowDuplicateTypesOnUnionExtensions(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Foo
        type Bar

        union MyUnion = Foo | Bar

        extend union MyUnion = Bar
        ';

        $this->expectExceptionObject(new DefinitionException(ASTHelper::duplicateDefinition('Bar')));
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
}
