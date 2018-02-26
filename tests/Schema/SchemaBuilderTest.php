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
}
