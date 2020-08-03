<?php

namespace Tests\Unit\Schema;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use Nuwave\Lighthouse\Schema\RootType;
use Tests\TestCase;

class SchemaBuilderTest extends TestCase
{
    public function testGeneratesValidSchema(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '');

        $this->assertInstanceOf(Schema::class, $schema);
        // This would throw if the schema were invalid
        $schema->assertValid();
    }

    public function testGeneratesWithEmptyQueryType(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type Query

        extend type Query {
            foo: Int
        }
        ');

        $this->assertInstanceOf(Schema::class, $schema);
        // This would throw if the schema were invalid
        $schema->assertValid();
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

        /** @var \GraphQL\Type\Definition\ObjectType $mutationObjectType */
        $mutationObjectType = $schema->getType(RootType::MUTATION);
        $foo = $mutationObjectType->getField('foo');

        $this->assertSame('foo', $foo->name);
    }

    public function testCanResolveEnumTypes(): void
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

        /** @var \GraphQL\Type\Definition\EnumType $enumType */
        $enumType = $schema->getType('Role');
        $this->assertInstanceOf(EnumType::class, $enumType);
        $this->assertSame('Role description', $enumType->description);

        $enumValues = $enumType->getValues();
        $this->assertCount(2, $enumValues);

        $enumValueDefinition = $enumType->getValue('ADMIN');
        $this->assertInstanceOf(EnumValueDefinition::class, $enumValueDefinition);
        /** @var \GraphQL\Type\Definition\EnumValueDefinition $enumValueDefinition */
        $this->assertSame('Company administrator.', $enumValueDefinition->description);
    }

    public function testCanResolveInterfaceTypes(): void
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

        /** @var \GraphQL\Type\Definition\InterfaceType $interfaceType */
        $interfaceType = $schema->getType('Foo');
        $this->assertInstanceOf(InterfaceType::class, $interfaceType);

        $this->assertSame('int', $interfaceType->description);
        $this->assertSame('bar is baz', $interfaceType->getField('bar')->description);
    }

    public function testCanResolveObjectTypes(): void
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
        $this->assertInstanceOf(ObjectType::class, $foo);
        /** @var \GraphQL\Type\Definition\ObjectType $foo */
        $this->assertSame('Foo', $foo->name);

        $bar = $foo->getField('bar');
        $this->assertInstanceOf(FieldDefinition::class, $bar);
        /** @var \GraphQL\Type\Definition\FieldDefinition $bar */
        $this->assertSame('bar attribute of Foo', $bar->description);

        $baz = $bar->getArg('baz');
        $this->assertInstanceOf(FieldArgument::class, $baz);
        /** @var \GraphQL\Type\Definition\FieldArgument $baz */
        $this->assertSame('arg', $baz->description);
        $this->assertTrue($baz->defaultValueExists());
        $this->assertFalse($baz->defaultValue);
    }

    public function testCanResolveInputObjectTypes(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        "bla"
        input CreateFoo {
            "xyz"
            foo: String!
            bar: Int = 123
        }
        ');

        /** @var \GraphQL\Type\Definition\InputObjectType $inputObjectType */
        $inputObjectType = $schema->getType('CreateFoo');
        $this->assertInstanceOf(InputObjectType::class, $inputObjectType);

        $this->assertSame('CreateFoo', $inputObjectType->name);
        $this->assertSame('bla', $inputObjectType->description);
        $this->assertSame('xyz', $inputObjectType->getField('foo')->description);
        $this->assertSame(123, $inputObjectType->getField('bar')->defaultValue);
    }

    public function testCanResolveMutations(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type Mutation {
            foo(bar: String! baz: String): String
        }
        ');

        /** @var \GraphQL\Type\Definition\ObjectType $mutationObjectType */
        $mutationObjectType = $schema->getType(RootType::MUTATION);
        $foo = $mutationObjectType->getField('foo');

        $this->assertSame('foo', $foo->name);
    }

    public function testCanResolveQueries(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '');

        /** @var \GraphQL\Type\Definition\ObjectType $queryObjectType */
        $queryObjectType = $schema->getType(RootType::QUERY);
        $field = $queryObjectType->getField('foo');

        $this->assertSame('foo', $field->name);
    }

    public function testCanExtendObjectTypes(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type Foo {
            bar: String!
        }

        extend type Foo {
            baz: String!
        }
        ');

        /** @var \GraphQL\Type\Definition\ObjectType $objectType */
        $objectType = $schema->getType('Foo');
        $fields = $objectType->config['fields']();

        $this->assertArrayHasKey('baz', $fields);
    }

    public function testCanExtendTypes(): void
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

        /** @var \GraphQL\Type\Definition\ObjectType $type */
        $type = $schema->getType('Foo');

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

        /** @var ObjectType $queryType */
        $queryType = $schema->getQueryType();

        $barArg = $queryType
            ->getField('foo')
            ->getArg('bar');

        $this->assertInstanceOf(FieldArgument::class, $barArg);
        /** @var \GraphQL\Type\Definition\FieldArgument $barArg */
        $this->assertSame('internal', $barArg->defaultValue);
    }
}
