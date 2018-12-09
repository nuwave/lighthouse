<?php

namespace Tests\Unit\Schema;

use Tests\TestCase;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\InputObjectType;

class SchemaBuilderTest extends TestCase
{
    /**
     * @test
     */
    public function itGeneratesValidSchema()
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('');

        $this->assertInstanceOf(Schema::class, $schema);
        // This would throw if the schema were invalid
        $schema->assertValid();
    }

    /**
     * @test
     */
    public function itCanResolveEnumTypes()
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('
        "Role description"
        enum Role {
            "Company administrator."
            ADMIN @enum(value:"admin")

            "Company employee."
            EMPLOYEE @enum(value:"employee")
        }
        ');

        /** @var EnumType $enum */
        $enum = $schema->getType('Role');
        $this->assertInstanceOf(EnumType::class, $enum);
        $this->assertSame('Role description', $enum->description);

        $enumValues = $enum->getValues();
        $this->assertCount(2, $enumValues);
        $this->assertSame('Company administrator.', $enum->getValue('ADMIN')->description);
    }

    /**
     * @test
     */
    public function itCanResolveInterfaceTypes()
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('
        """
        int
        """
        interface Foo {
            "bar is baz"
            bar: String!
        }
        ');

        /** @var InterfaceType $interface */
        $interface = $schema->getType('Foo');
        $this->assertInstanceOf(InterfaceType::class, $interface);

        $this->assertSame('int', $interface->description);
        $this->assertSame('bar is baz', $interface->getField('bar')->description);
    }

    /**
     * @test
     */
    public function itCanResolveObjectTypes()
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('
        "asdf"
        type Foo {
            "bar attribute of Foo"
            bar(
                "arg"
                baz: Boolean = false
            ): String!
        }
        ');

        /** @var ObjectType $foo */
        $foo = $schema->getType('Foo');
        $this->assertInstanceOf(ObjectType::class, $foo);

        $this->assertSame('Foo', $foo->name);

        $bar = $foo->getField('bar');
        $this->assertSame('bar attribute of Foo', $bar->description);

        $baz = $bar->getArg('baz');
        $this->assertSame('arg', $baz->description);
        $this->assertTrue($baz->defaultValueExists());
        $this->assertFalse($baz->defaultValue);
    }

    /**
     * @test
     */
    public function itCanResolveInputObjectTypes()
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('
        "bla"
        input CreateFoo {
            "xyz"
            foo: String!
            bar: Int = 123
        }
        ');

        /** @var InputObjectType $createFoo */
        $createFoo = $schema->getType('CreateFoo');
        $this->assertInstanceOf(InputObjectType::class, $createFoo);

        $this->assertSame('CreateFoo', $createFoo->name);
        $this->assertSame('bla', $createFoo->description);
        $this->assertSame('xyz', $createFoo->getField('foo')->description);
        $this->assertSame(123, $createFoo->getField('bar')->defaultValue);
    }

    /**
     * @test
     */
    public function itCanResolveMutations()
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('
        type Mutation {
            foo(bar: String! baz: String): String
        }
        ');

        /** @var ObjectType $mutation */
        $mutation = $schema->getType('Mutation');
        $foo = $mutation->getField('foo');

        $this->assertSame('foo', $foo->name);
    }

    /**
     * @test
     */
    public function itCanResolveQueries()
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('');

        /** @var ObjectType $type */
        $type = $schema->getType('Query');
        $field = $type->getField('foo');

        $this->assertSame('foo', $field->name);
    }

    /**
     * @test
     */
    public function itCanExtendObjectTypes()
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('
        type Foo {
            bar: String!
        }
        
        extend type Foo {
            baz: String!
        }
        ');

        $type = $schema->getType('Foo');
        $fields = $type->config['fields']();
        $this->assertArrayHasKey('baz', $fields);
    }

    /**
     * @test
     */
    public function itCanExtendTypes()
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('
        type Foo {
            foo: String!
        }
        
        extend type Foo {
            "yo?"
            bar: String!
        }
        ');

        /** @var ObjectType $type */
        $type = $schema->getType('Foo');

        $this->assertSame('yo?', $type->getField('bar')->description);
    }
}
