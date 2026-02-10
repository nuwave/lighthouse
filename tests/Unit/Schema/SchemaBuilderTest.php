<?php declare(strict_types=1);

namespace Tests\Unit\Schema;

use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Tests\TestCase;

final class SchemaBuilderTest extends TestCase
{
    public function testGeneratesValidSchema(): void
    {
        $this->buildSchemaWithPlaceholderQuery('')
            ->assertValid();

        $schemaBuilder = $this->app->make(SchemaBuilder::class);
        $this->assertNotEmpty($schemaBuilder->schemaHash());
    }

    public function testGeneratesWithEmptyQueryType(): void
    {
        $this
            ->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
            type Query

            extend type Query {
                foo: Int
            }
            GRAPHQL)
            ->assertValid();

        $this->expectNotToPerformAssertions();
    }

    public function testGeneratesWithEmptyMutationType(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type Query

        type Mutation

        extend type Mutation {
            foo(bar: String! baz: String): String
        }
        GRAPHQL);

        $mutationObjectType = $schema->getType(RootType::MUTATION);
        $this->assertInstanceOf(ObjectType::class, $mutationObjectType);

        $this->assertSame('foo', $mutationObjectType->getField('foo')->name);
    }

    public function testResolveEnumTypes(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ <<<'GRAPHQL'
        "Role description"
        enum Role {
            "Company administrator."
            ADMIN @enum(value: "admin")

            "Company employee."
            EMPLOYEE @enum(value: "employee")
        }
        GRAPHQL);

        $enumType = $schema->getType('Role');
        $this->assertInstanceOf(EnumType::class, $enumType);
        $this->assertSame('Role description', $enumType->description);

        $enumValues = $enumType->getValues();
        $this->assertCount(2, $enumValues);

        $enumValueDefinition = $enumType->getValue('ADMIN');
        $this->assertInstanceOf(EnumValueDefinition::class, $enumValueDefinition);
        $this->assertSame('Company administrator.', $enumValueDefinition->description);
    }

    public function testResolveInterfaceTypes(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ <<<'GRAPHQL'
        """
        int
        """
        interface Foo {
            "bar is baz"
            bar: String!
        }
        GRAPHQL);

        $interfaceType = $schema->getType('Foo');
        $this->assertInstanceOf(InterfaceType::class, $interfaceType);

        $this->assertSame('int', $interfaceType->description);
        $this->assertSame('bar is baz', $interfaceType->getField('bar')->description);
    }

    public function testResolveObjectTypes(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ <<<'GRAPHQL'
        "asdf"
        type Foo {
            "bar attribute of Foo"
            bar(
                "arg"
                baz: Boolean = false
            ): String!
        }
        GRAPHQL);

        $foo = $schema->getType('Foo');
        $this->assertInstanceOf(ObjectType::class, $foo);
        $this->assertSame('Foo', $foo->name);

        $bar = $foo->getField('bar');
        $this->assertSame('bar attribute of Foo', $bar->description);

        $baz = $bar->getArg('baz');
        $this->assertInstanceOf(Argument::class, $baz);
        $this->assertSame('arg', $baz->description);
        $this->assertTrue($baz->defaultValueExists());
        $this->assertFalse($baz->defaultValue);
    }

    public function testResolveInputObjectTypes(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ <<<'GRAPHQL'
        "bla"
        input CreateFoo {
            "xyz"
            foo: String!
            bar: Int = 123
        }
        GRAPHQL);

        $inputObjectType = $schema->getType('CreateFoo');
        $this->assertInstanceOf(InputObjectType::class, $inputObjectType);

        $this->assertSame('CreateFoo', $inputObjectType->name);
        $this->assertSame('bla', $inputObjectType->description);
        $this->assertSame('xyz', $inputObjectType->getField('foo')->description);
        $this->assertSame(123, $inputObjectType->getField('bar')->defaultValue);
    }

    public function testResolveMutations(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            foo(bar: String! baz: String): String
        }
        GRAPHQL);

        $mutationObjectType = $schema->getType(RootType::MUTATION);
        $this->assertInstanceOf(ObjectType::class, $mutationObjectType);

        $this->assertSame('foo', $mutationObjectType->getField('foo')->name);
    }

    public function testResolveQueries(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('');

        $queryObjectType = $schema->getType(RootType::QUERY);
        $this->assertInstanceOf(ObjectType::class, $queryObjectType);

        $this->assertSame('foo', $queryObjectType->getField('foo')->name);
    }

    public function testExtendObjectTypes(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ <<<'GRAPHQL'
        type Foo {
            bar: String!
        }

        extend type Foo {
            baz: String!
        }
        GRAPHQL);

        $objectType = $schema->getType('Foo');
        $this->assertInstanceOf(ObjectType::class, $objectType);
        $fields = $objectType->getFields();

        $this->assertArrayHasKey('baz', $fields);
    }

    public function testExtendTypes(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ <<<'GRAPHQL'
        type Foo {
            foo: String!
        }

        extend type Foo {
            "yo?"
            bar: String!
        }
        GRAPHQL);

        $type = $schema->getType('Foo');
        $this->assertInstanceOf(ObjectType::class, $type);

        $this->assertSame('yo?', $type->getField('bar')->description);
    }

    public function testResolvesEnumDefaultValuesToInternalValues(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                bar: Baz = FOOBAR
            ): Int
        }

        enum Baz {
            FOOBAR @enum(value: "internal")
        }
        GRAPHQL);

        $queryType = $schema->getQueryType();
        $this->assertInstanceOf(ObjectType::class, $queryType);

        $barArg = $queryType
            ->getField('foo')
            ->getArg('bar');
        $this->assertInstanceOf(Argument::class, $barArg);
        $this->assertSame('internal', $barArg->defaultValue);
    }
}
