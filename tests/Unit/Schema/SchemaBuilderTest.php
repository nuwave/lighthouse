<?php

namespace Tests\Unit\Schema;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Schema;
use Tests\TestCase;

class SchemaBuilderTest extends TestCase
{
    /**
     * Get test environment setup.
     *
     * @param mixed $app
     */
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set(
            'lighthouse.namespaces.queries',
            'Tests\\Utils\\Mutations'
        );

        $app['config']->set(
            'lighthouse.namespaces.mutations',
            'Tests\\Utils\\Mutations'
        );
    }

    /**
     * @test
     */
    public function itCanResolveEnumTypes()
    {
        $schema = $this->buildSchemaWithDefaultQuery('
        enum Role {
            "Company administrator."
            admin @enum(value:"admin")

            "Company employee."
            employee @enum(value:"employee")
        }
        ');

        $this->assertInstanceOf(EnumType::class, $schema->getType('Role'));
    }

    /**
     * @test
     */
    public function itCanResolveInterfaceTypes()
    {
        $schema = $this->buildSchemaWithDefaultQuery('
        interface Foo {
            "bar is baz"
            bar: String!
        }
        ');

        $this->assertInstanceOf(InterfaceType::class, $schema->getType('Foo'));
    }

    /**
     * @test
     */
    public function itCanResolveObjectTypes()
    {
        $schema = $this->buildSchemaWithDefaultQuery('
        type Foo {
            "bar attribute of Foo"
            bar: String!
        }
        ');

        $foo = $schema->getType('Foo');
        $this->assertInstanceOf(ObjectType::class, $foo);

        $config = $foo->config;
        $this->assertEquals('Foo', data_get($config, 'name'));

        $resolvedFields = $config['fields']();
        $this->assertEquals('bar attribute of Foo', $resolvedFields['bar']['description']->value);
    }

    /**
     * @test
     */
    public function itCanResolveInputObjectTypes()
    {
        $schema = $this->buildSchemaWithDefaultQuery('
        input CreateFoo {
            foo: String!
            bar: Int
        }
        ');

        $createFoo = $schema->getType('CreateFoo');
        $this->assertInstanceOf(InputType::class, $createFoo);

        $config = $createFoo->config;
        $fields = $config['fields']();
        $this->assertEquals('CreateFoo', data_get($config, 'name'));
        $this->assertArrayHasKey('foo', $fields);
        $this->assertArrayHasKey('bar', $fields);
    }

    /**
     * @test
     */
    public function itCanResolveMutations()
    {
        $schema = $this->buildSchemaWithDefaultQuery('
        type Mutation {
            foo(bar: String! baz: String): String
        }
        ');

        $type = $schema->getType('Mutation');
        $mutation = $type->config['fields']()['foo'];

        $this->assertArrayHasKey('args', $mutation);
        $this->assertArrayHasKey('type', $mutation);
        $this->assertArrayHasKey('resolve', $mutation);
        $this->assertArrayHasKey('bar', $mutation['args']);
        $this->assertArrayHasKey('baz', $mutation['args']);
    }

    /**
     * @test
     */
    public function itCanResolveQueries()
    {
        $schema = $this->buildSchemaFromString('
        type Query {
            foo(bar: String! baz: String): String
        }
        ');

        $type = $schema->getType('Query');
        $query = $type->config['fields']()['foo'];

        $this->assertArrayHasKey('args', $query);
        $this->assertArrayHasKey('type', $query);
        $this->assertArrayHasKey('resolve', $query);
        $this->assertArrayHasKey('bar', $query['args']);
        $this->assertArrayHasKey('baz', $query['args']);
    }

    /**
     * @test
     */
    public function itCanExtendObjectTypes()
    {
        $schema = $this->buildSchemaWithDefaultQuery('
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
    public function itCanExtendQuery()
    {
        $schema = $this->buildSchemaFromString('
        type Query {
            foo: String!
        }
        extend type Query {
            bar: String!
        }
        ');

        $type = $schema->getType('Query');
        $fields = $type->config['fields']();
        $this->assertArrayHasKey('bar', $fields);
    }

    /**
     * @test
     */
    public function itCanExtendMutation()
    {
        $schema = $this->buildSchemaWithDefaultQuery('
        type Mutation {
            foo: String!
        }
        extend type Mutation {
            bar: String!
        }
        ');

        $type = $schema->getType('Mutation');
        $fields = $type->config['fields']();
        $this->assertArrayHasKey('bar', $fields);
    }

    /**
     * @test
     */
    public function itCanGenerateGraphQLSchema()
    {
        $schema = $this->buildSchemaFromString('
            type Query {
                foo: String!
            }

            type Mutation {
                foo: String!
            }
        ');

        $this->assertInstanceOf(Schema::class, $schema);
        // This would throw if the schema were invalid
        $schema->assertValid();
    }
}
