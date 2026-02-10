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
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo: String
                }
        
                extend type Query {
                    bar: Int!
                }
        
                extend type Query {
                    baz: Boolean
                }
        GRAPHQL;
        $documentAST = $this->astBuilder->documentAST();

        $queryType = $documentAST->types[RootType::QUERY];
        $this->assertInstanceOf(ObjectTypeDefinitionNode::class, $queryType);

        $this->assertCount(3, $queryType->fields);
    }

    public function testMergeTypeExtensionDirectives(): void
    {
        $directive = new class() extends BaseDirective {
            public static function definition(): string
            {
                return /** @lang GraphQL */ <<<'GRAPHQL'
                directive @foo repeatable on OBJECT
                GRAPHQL;
            }
        };

        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('foo', $directive::class);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type MyType {
                    field: String
                }
        
                extend type MyType @foo
        
                extend type MyType @foo
        GRAPHQL;
        $documentAST = $this->astBuilder->documentAST();

        $myType = $documentAST->types['MyType'];
        $this->assertInstanceOf(ObjectTypeDefinitionNode::class, $myType);

        $this->assertCount(2, $myType->directives);
    }

    public function testAllowsExtendingUndefinedRootTypes(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                extend type Query {
                    foo: ID
                }
        
                extend type Mutation {
                    bar: ID
                }
        
                extend type Subscription {
                    baz: ID
                }
        GRAPHQL;
        $documentAST = $this->astBuilder->documentAST();

        $queryType = $documentAST->types[RootType::QUERY];
        $this->assertInstanceOf(ObjectTypeDefinitionNode::class, $queryType);

        $this->assertCount(1, $queryType->fields);

        $mutationType = $documentAST->types[RootType::MUTATION];
        $this->assertInstanceOf(ObjectTypeDefinitionNode::class, $mutationType);

        $this->assertCount(1, $mutationType->fields);

        $subscriptionType = $documentAST->types[RootType::SUBSCRIPTION];
        $this->assertInstanceOf(ObjectTypeDefinitionNode::class, $subscriptionType);

        $this->assertCount(1, $subscriptionType->fields);
    }

    public function testMergeInputExtensionFields(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                input Inputs {
                    foo: String
                }
        
                extend input Inputs {
                    bar: Int!
                }
        
                extend input Inputs {
                    baz: Boolean
                }
        GRAPHQL;
        $documentAST = $this->astBuilder->documentAST();

        $inputs = $documentAST->types['Inputs'];
        $this->assertInstanceOf(InputObjectTypeDefinitionNode::class, $inputs);

        $this->assertCount(3, $inputs->fields);
    }

    public function testMergeInputExtensionDirectives(): void
    {
        $directive = new class() extends BaseDirective {
            public static function definition(): string
            {
                return /** @lang GraphQL */ <<<'GRAPHQL'
                directive @foo repeatable on INPUT_OBJECT
                GRAPHQL;
            }
        };

        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('foo', $directive::class);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                input MyInput {
                    field: String
                }
        
                extend input MyInput @foo
        
                extend input MyInput @foo
        GRAPHQL;
        $documentAST = $this->astBuilder->documentAST();

        $myInput = $documentAST->types['MyInput'];
        $this->assertInstanceOf(InputObjectTypeDefinitionNode::class, $myInput);

        $this->assertCount(2, $myInput->directives);
    }

    public function testMergeInterfaceExtensionFields(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                interface Named {
                  name: String!
                }
        
                extend interface Named {
                  bar: Int!
                }
        
                extend interface Named {
                  baz: Boolean
                }
        GRAPHQL;
        $documentAST = $this->astBuilder->documentAST();

        $named = $documentAST->types['Named'];
        $this->assertInstanceOf(InterfaceTypeDefinitionNode::class, $named);

        $this->assertCount(3, $named->fields);
    }

    public function testMergeInterfaceExtensionDirectives(): void
    {
        $directive = new class() extends BaseDirective {
            public static function definition(): string
            {
                return /** @lang GraphQL */ <<<'GRAPHQL'
                directive @foo repeatable on INTERFACE
                GRAPHQL;
            }
        };

        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('foo', $directive::class);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                interface MyInterface {
                    field: String
                }
        
                extend interface MyInterface @foo
        
                extend interface MyInterface @foo
        GRAPHQL;
        $documentAST = $this->astBuilder->documentAST();

        $myInterface = $documentAST->types['MyInterface'];
        $this->assertInstanceOf(InterfaceTypeDefinitionNode::class, $myInterface);

        $this->assertCount(2, $myInterface->directives);
    }

    public function testMergeScalarExtensionDirectives(): void
    {
        $directive = new class() extends BaseDirective {
            public static function definition(): string
            {
                return /** @lang GraphQL */ <<<'GRAPHQL'
                directive @foo repeatable on SCALAR
                GRAPHQL;
            }
        };

        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('foo', $directive::class);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                scalar MyScalar
        
                extend scalar MyScalar @foo
        
                extend scalar MyScalar @foo
        GRAPHQL;
        $documentAST = $this->astBuilder->documentAST();

        $myScalar = $documentAST->types['MyScalar'];
        $this->assertInstanceOf(ScalarTypeDefinitionNode::class, $myScalar);

        $this->assertCount(2, $myScalar->directives);
    }

    public function testMergeEnumExtensionFields(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;
        $documentAST = $this->astBuilder->documentAST();

        $myEnum = $documentAST->types['MyEnum'];
        $this->assertInstanceOf(EnumTypeDefinitionNode::class, $myEnum);

        $this->assertCount(4, $myEnum->values);
    }

    public function testMergeEnumExtensionDirectives(): void
    {
        $directive = new class() extends BaseDirective {
            public static function definition(): string
            {
                return /** @lang GraphQL */ <<<'GRAPHQL'
                directive @foo repeatable on ENUM
                GRAPHQL;
            }
        };

        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('foo', $directive::class);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                enum MyEnum {
                    ONE
                    TWO
                }
        
                extend enum MyEnum @foo
        
                extend enum MyEnum @foo
        GRAPHQL;
        $documentAST = $this->astBuilder->documentAST();

        $myEnum = $documentAST->types['MyEnum'];
        $this->assertInstanceOf(EnumTypeDefinitionNode::class, $myEnum);

        $this->assertCount(2, $myEnum->directives);
    }

    public function testMergeUnionExtensionFields(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                    type Foo
                    type Bar
                    type Baz
        
                    union MyUnion = Foo
        
                    extend union MyUnion = Bar
        
                    extend union MyUnion = Baz
        GRAPHQL;
        $documentAST = $this->astBuilder->documentAST();

        $myUnion = $documentAST->types['MyUnion'];
        $this->assertInstanceOf(UnionTypeDefinitionNode::class, $myUnion);

        $this->assertCount(3, $myUnion->types);
    }

    public function testDoesNotAllowExtendingUndefinedScalar(): void
    {
        $directive = new class() extends BaseDirective {
            public static function definition(): string
            {
                return /** @lang GraphQL */ <<<'GRAPHQL'
                directive @foo repeatable on SCALAR
                GRAPHQL;
            }
        };

        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('foo', $directive::class);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                extend scalar MyScalar @foo
        GRAPHQL;

        $this->expectExceptionObject(new DefinitionException('Could not find a base definition MyScalar of kind ' . NodeKind::SCALAR_TYPE_EXTENSION . ' to extend.'));
        $this->astBuilder->documentAST();
    }

    public function testDoesNotAllowExtendingUndefinedTypes(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo: String
                }
        
                extend type Foo {
                    foo: Int
                }
        GRAPHQL;

        $this->expectExceptionObject(new DefinitionException('Could not find a base definition Foo of kind ' . NodeKind::OBJECT_TYPE_EXTENSION . ' to extend.'));
        $this->astBuilder->documentAST();
    }

    public function testDoesNotAllowExtendingUndefinedUnions(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                union MyFirstEnum = String
        
                extend union MySecondUnion = Int
        GRAPHQL;

        $this->expectExceptionObject(new DefinitionException('Could not find a base definition MySecondUnion of kind ' . NodeKind::UNION_TYPE_EXTENSION . ' to extend.'));
        $this->astBuilder->documentAST();
    }

    public function testDoesNotAllowDuplicateFieldsOnTypeExtensions(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo: String
                }
        
                extend type Query {
                    foo: Int
                }
        GRAPHQL;

        $this->expectExceptionObject(new DefinitionException(ASTHelper::duplicateDefinition('foo')));
        $this->astBuilder->documentAST();
    }

    public function testDoesNotAllowDuplicateFieldsOnInputExtensions(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                input Inputs {
                    foo: String
                }
        
                extend input Inputs {
                    foo: Int
                }
        GRAPHQL;

        $this->expectExceptionObject(new DefinitionException(ASTHelper::duplicateDefinition('foo')));
        $this->astBuilder->documentAST();
    }

    public function testDoesNotAllowDuplicateFieldsOnInterfaceExtensions(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                interface Named {
                    foo: String
                }
        
                extend interface Named{
                    foo: Int
                }
        GRAPHQL;

        $this->expectExceptionObject(new DefinitionException(ASTHelper::duplicateDefinition('foo')));
        $this->astBuilder->documentAST();
    }

    public function testDoesNotAllowDuplicateValuesOnEnumExtensions(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                enum MyEnum {
                    ONE
                    TWO
                }
        
                extend enum MyEnum {
                    TWO
                    THREE
                }
        GRAPHQL;

        $this->expectExceptionObject(new DefinitionException(ASTHelper::duplicateDefinition('TWO')));
        $this->astBuilder->documentAST();
    }

    public function testDoesNotAllowDuplicateTypesOnUnionExtensions(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Foo
                type Bar
        
                union MyUnion = Foo | Bar
        
                extend union MyUnion = Bar
        GRAPHQL;

        $this->expectExceptionObject(new DefinitionException(ASTHelper::duplicateDefinition('Bar')));
        $this->astBuilder->documentAST();
    }

    public function testDoesNotAllowMergingNonMatchingTypes(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Foo {
                    bar: ID
                }
        
                extend interface Foo {
                    baz: ID
                }
        GRAPHQL;

        $this->expectExceptionObject(new DefinitionException('The type extension Foo of kind ' . NodeKind::INTERFACE_TYPE_EXTENSION . ' can not extend a definition of kind ' . NodeKind::OBJECT_TYPE_DEFINITION . '.'));
        $this->astBuilder->documentAST();
    }

    public function testMergeTypeExtensionInterfaces(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;
        $documentAST = $this->astBuilder->documentAST();

        $userType = $documentAST->types['User'];
        $this->assertInstanceOf(ObjectTypeDefinitionNode::class, $userType);

        $interfaces = new Collection($userType->interfaces);
        $this->assertCount(2, $interfaces);
        $this->assertTrue($interfaces->contains('name.value', 'Emailable'));
        $this->assertTrue($interfaces->contains('name.value', 'Nameable'));
    }
}
