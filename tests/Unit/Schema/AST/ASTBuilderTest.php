<?php

namespace Tests\Unit\Schema\AST;

use GraphQL\Language\AST\NodeKind;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\RootType;
use Tests\TestCase;

class ASTBuilderTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\AST\ASTBuilder
     */
    protected $astBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->astBuilder = app(ASTBuilder::class);
    }

    public function testCanMergeTypeExtensionFields(): void
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

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
        $queryType = $documentAST->types[RootType::QUERY];

        $this->assertCount(
            3,
            $queryType->fields
        );
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

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
        $queryType = $documentAST->types[RootType::QUERY];
        $this->assertCount(
            1,
            $queryType->fields
        );

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $mutationType */
        $mutationType = $documentAST->types[RootType::MUTATION];
        $this->assertCount(
            1,
            $mutationType->fields
        );

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $subscriptionType */
        $subscriptionType = $documentAST->types[RootType::SUBSCRIPTION];
        $this->assertCount(
            1,
            $subscriptionType->fields
        );
    }

    public function testCanMergeInputExtensionFields(): void
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

        /** @var \GraphQL\Language\AST\InputObjectTypeDefinitionNode $inputs */
        $inputs = $documentAST->types['Inputs'];
        $this->assertCount(
            3,
            $inputs->fields
        );
    }

    public function testCanMergeInterfaceExtensionFields(): void
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

        /** @var \GraphQL\Language\AST\InterfaceTypeDefinitionNode $named */
        $named = $documentAST->types['Named'];
        $this->assertCount(3, $named->fields);
    }

    public function testCanMergeEnumExtensionFields(): void
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

        /** @var \GraphQL\Language\AST\EnumTypeDefinitionNode $myEnum */
        $myEnum = $documentAST->types['MyEnum'];
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

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('Could not find a base definition Foo of kind '.NodeKind::OBJECT_TYPE_EXTENSION.' to extend.');
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

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage(ASTHelper::duplicateDefinition('foo'));
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

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage(ASTHelper::duplicateDefinition('foo'));
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

        $this->expectException(DefinitionException::class);
        $this->expectException(DefinitionException::class);
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

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage(ASTHelper::duplicateDefinition('TWO'));
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

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('The type extension Foo of kind '.NodeKind::INTERFACE_TYPE_EXTENSION.' can not extend a definition of kind '.NodeKind::OBJECT_TYPE_DEFINITION.'.');
        $this->astBuilder->documentAST();
    }
}
