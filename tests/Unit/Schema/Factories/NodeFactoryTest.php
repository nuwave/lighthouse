<?php

namespace Tests\Unit\Schema\Factories;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Schema\Factories\NodeFactory;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Tests\TestCase;

class NodeFactoryTest extends TestCase
{
    /**
     * Node factory.
     *
     * @var NodeFactory
     */
    protected $factory;

    /**
     * Setup test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->factory = new NodeFactory();
    }

    /**
     * Set up application environment.
     *
     * @param \Illuminate\Support\Facades\App $app
     */
    protected function getEnvironmentSetup($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('lighthouse.namespaces.scalars', 'Nuwave\Lighthouse\Schema\Types\Scalars');
    }

    /**
     * @test
     */
    public function itCanTransformEnums()
    {
        $schema = '
        enum Role {
            # Company administrator.
            ADMIN @enum(value:"admin")

            # Company employee.
            EMPLOYEE @enum(value:"employee")
        }
        ';

        $enumNode = $this->parse($schema)->definitions[0];
        $type = $this->factory->handle(new NodeValue($enumNode));

        $this->assertInstanceOf(EnumType::class, $type);
        $this->assertEquals('Role', $type->name);
    }

    /**
     * @test
     */
    public function itCanTransformScalars()
    {
        $schema = '
        scalar DateTime @scalar(class:"DateTime")
        ';

        $scalarNode = $this->parse($schema)->definitions[0];
        $type = $this->factory->handle(new NodeValue($scalarNode));

        $this->assertInstanceOf(ScalarType::class, $type);
        $this->assertEquals('DateTime', $type->name);
    }

    /**
     * @test
     */
    public function itCanTransformInterfaces()
    {
        $schema = '
        interface Node {
            _id: ID!
        }
        ';

        $interface = $this->parse($schema)->definitions[0];
        $type = $this->factory->handle(new NodeValue($interface));

        $this->assertInstanceOf(InterfaceType::class, $type);
        $this->assertEquals('Node', $type->name);
        $this->assertArrayHasKey('_id', $type->config['fields']);
    }

    /**
     * @test
     */
    public function itCanTransformObjectTypes()
    {
        $schema = '
        type User {
            foo(bar: String! @bcrypt): String!
        }
        ';

        $interface = $this->parse($schema)->definitions[0];
        $type = $this->factory->handle(new NodeValue($interface));

        $this->assertInstanceOf(ObjectType::class, $type);
        $this->assertEquals('User', $type->name);
        $this->assertArrayHasKey('foo', $type->config['fields']);
    }

    /**
     * @test
     */
    public function itCanTransformInputObjectTypes()
    {
        $schema = '
        input UserInput {
            foo: String!
        }
        ';

        $interface = $this->parse($schema)->definitions[0];
        $type = $this->factory->handle(new NodeValue($interface));

        $this->assertInstanceOf(InputObjectType::class, $type);
        $this->assertEquals('UserInput', $type->name);
        $this->assertArrayHasKey('foo', $type->config['fields']);
    }
}
