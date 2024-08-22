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

        $this->assertNotNull($this->app->make(SchemaBuilder::class)->schemaHash());
    }

    public function testGeneratesWithEmptyQueryType(): void
    {
        $this
            ->buildSchema(/** @lang GraphQL */ '
            type Query

            extend type Query {
                foo: Int
            }
            ')
            ->assertValid();

        $this->expectNotToPerformAssertions();
    }

    public function testGeneratesWithEmptyMutationType(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type Query

        type Mutation

        extend type Mutation {
            foo(bar: String! baz: String): String
        }
        ');

        $mutationObjectType = $schema->getType(RootType::MUTATION);
        assert($mutationObjectType instanceof ObjectType);

        $this->assertSame('foo', $mutationObjectType->getField('foo')->name);
    }

    public function testResolveEnumTypes(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        "Role description"
        enum Role {
            "Company administrator."
            ADMIN @enum(value: "admin")

            "Company employee."
            EMPLOYEE @enum(value: "employee")
        }
        ');

        $enumType = $schema->getType('Role');
        assert($enumType instanceof EnumType);
        $this->assertSame('Role description', $enumType->description);

        $enumValues = $enumType->getValues();
        $this->assertCount(2, $enumValues);

        $enumValueDefinition = $enumType->getValue('ADMIN');
        assert($enumValueDefinition instanceof EnumValueDefinition);
        $this->assertSame('Company administrator.', $enumValueDefinition->description);
    }

    public function testResolveInterfaceTypes(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        """
        int
        """
        interface Foo {
            "bar is baz"
            bar: String!
        }
        ');

        $interfaceType = $schema->getType('Foo');
        assert($interfaceType instanceof InterfaceType);

        $this->assertSame('int', $interfaceType->description);
        $this->assertSame('bar is baz', $interfaceType->getField('bar')->description);
    }

    public function testResolveObjectTypes(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        "asdf"
        type Foo {
            "bar attribute of Foo"
            bar(
                "arg"
                baz: Boolean = false
            ): String!
        }
        ');

        $foo = $schema->getType('Foo');
        assert($foo instanceof ObjectType);
        $this->assertSame('Foo', $foo->name);

        $bar = $foo->getField('bar');
        $this->assertSame('bar attribute of Foo', $bar->description);

        $baz = $bar->getArg('baz');
        assert($baz instanceof Argument);
        $this->assertSame('arg', $baz->description);
        $this->assertTrue($baz->defaultValueExists());
        $this->assertFalse($baz->defaultValue);
    }

    public function testResolveInputObjectTypes(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        "bla"
        input CreateFoo {
            "xyz"
            foo: String!
            bar: Int = 123
        }
        ');

        $inputObjectType = $schema->getType('CreateFoo');
        assert($inputObjectType instanceof InputObjectType);

        $this->assertSame('CreateFoo', $inputObjectType->name);
        $this->assertSame('bla', $inputObjectType->description);
        $this->assertSame('xyz', $inputObjectType->getField('foo')->description);
        $this->assertSame(123, $inputObjectType->getField('bar')->defaultValue);
    }

    public function testResolveMutations(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type Mutation {
            foo(bar: String! baz: String): String
        }
        ');

        $mutationObjectType = $schema->getType(RootType::MUTATION);
        assert($mutationObjectType instanceof ObjectType);

        $this->assertSame('foo', $mutationObjectType->getField('foo')->name);
    }

    public function testResolveQueries(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('');

        $queryObjectType = $schema->getType(RootType::QUERY);
        assert($queryObjectType instanceof ObjectType);

        $this->assertSame('foo', $queryObjectType->getField('foo')->name);
    }

    public function testExtendObjectTypes(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type Foo {
            bar: String!
        }

        extend type Foo {
            baz: String!
        }
        ');

        $objectType = $schema->getType('Foo');
        assert($objectType instanceof ObjectType);
        $fields = $objectType->getFields();

        $this->assertArrayHasKey('baz', $fields);
    }

    public function testExtendTypes(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type Foo {
            foo: String!
        }

        extend type Foo {
            "yo?"
            bar: String!
        }
        ');

        $type = $schema->getType('Foo');
        assert($type instanceof ObjectType);

        $this->assertSame('yo?', $type->getField('bar')->description);
    }

    public function testResolvesEnumDefaultValuesToInternalValues(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            foo(
                bar: Baz = FOOBAR
            ): Int
        }

        enum Baz {
            FOOBAR @enum(value: "internal")
        }
        ');

        $queryType = $schema->getQueryType();
        assert($queryType instanceof ObjectType);

        $barArg = $queryType
            ->getField('foo')
            ->getArg('bar');
        assert($barArg instanceof Argument);
        $this->assertSame('internal', $barArg->defaultValue);
    }
}
