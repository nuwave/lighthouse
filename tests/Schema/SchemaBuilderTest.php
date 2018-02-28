<?php

namespace Nuwave\Lighthouse\Tests\Schema;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Tests\TestCase;

class SchemaBuilderTest extends TestCase
{
    /**
     * @test
     */
    public function itCanResolveEnumTypes()
    {
        $schema = '
        enum Role {
            # Company administrator.
            admin @enum(value:"admin")

            # Company employee.
            employee @enum(value:"employee")
        }
        ';

        $types = schema()->register($schema);
        $this->assertInstanceOf(EnumType::class, $types->first());
    }

    /**
     * @test
     */
    public function itCanResolveInterfaceTypes()
    {
        $schema = '
        interface Foo {
            # bar is baz
            bar: String!
        }
        ';

        $types = schema()->register($schema);
        $this->assertInstanceOf(InterfaceType::class, $types->first());
    }

    /**
     * @test
     */
    public function itCanResolveScalarTypes()
    {
        $schema = '
        scalar DateTime @scalar(class:"DateTime")
        ';

        $this->app['config']->set('lighthouse.namespaces.scalars', 'Nuwave\Lighthouse\Schema\Types\Scalars');
        $types = schema()->register($schema);
        $this->assertInstanceOf(ScalarType::class, $types->first());
    }

    /**
     * @test
     */
    public function itCanResolveObjectTypes()
    {
        $schema = '
        type Foo {
            # bar attribute of Foo
            bar: String!
        }
        ';

        $types = schema()->register($schema);
        $this->assertInstanceOf(ObjectType::class, $types->first());

        $config = $types->first()->config;
        $this->assertEquals('Foo', data_get($config, 'name'));
        $this->assertInstanceOf(\Closure::class, data_get($config, 'fields'));

        $fields = $config['fields']();
        $this->assertEquals('bar attribute of Foo', array_get($fields, 'bar.description'));
    }

    /**
     * @test
     */
    public function itCanResolveInputObjectTypes()
    {
        $schema = '
        input CreateFoo {
            foo: String!
            bar: Int
        }
        ';

        $types = schema()->register($schema);
        $this->assertInstanceOf(InputType::class, $types->first());

        $config = $types->first()->config;
        $this->assertEquals('CreateFoo', data_get($config, 'name'));
        $this->assertArrayHasKey('foo', data_get($config, 'fields'));
        $this->assertArrayHasKey('bar', data_get($config, 'fields'));
    }

    /**
     * @test
     */
    public function itCanResolveMutations()
    {
        $this->app['config']->set(
            'lighthouse.namespaces.mutations',
            'Nuwave\\Lighthouse\\Tests\\Utils\\Mutations'
        );

        $schema = '
        type Mutation {
            foo(bar: String! baz: String): String
        }
        ';

        $mutation = schema()->register($schema)->first();

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
        $this->app['config']->set(
            'lighthouse.namespaces.queries',
            'Nuwave\\Lighthouse\\Tests\\Utils\\Mutations'
        );

        $schema = '
        type Query {
            foo(bar: String! baz: String): String
        }
        ';

        $query = schema()->register($schema)->first();

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
        $schema = '
        type Foo {
            bar: String!
        }
        extend type Foo {
            baz: String!
        }
        ';

        $type = schema()->register($schema)->first();
        $fields = $type->config['fields']();
        $this->assertArrayHasKey('baz', $fields);
    }

    /**
     * @test
     */
    public function itCanExtendQuery()
    {
        $this->app['config']->set(
            'lighthouse.namespaces.queries',
            'Nuwave\\Lighthouse\\Tests\\Utils\\Mutations'
        );

        $schema = '
        type Query {
            bar: String!
        }
        extend type Query {
            baz: String!
        }
        ';

        $queries = schema()->register($schema)->toArray();
        $this->assertArrayHasKey('baz', $queries);
    }

    /**
     * @test
     */
    public function itCanExtendMutation()
    {
        $this->app['config']->set(
            'lighthouse.namespaces.mutations',
            'Nuwave\\Lighthouse\\Tests\\Utils\\Mutations'
        );

        $schema = '
        type Mutation {
            foo: String!
        }
        extend type Mutation {
            bar: String!
        }
        ';

        $mutations = schema()->register($schema)->toArray();
        $this->assertArrayHasKey('bar', $mutations);
    }
}
