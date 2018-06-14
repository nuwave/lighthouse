<?php

namespace Tests\Unit\Schema\Factories;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Factories\TypeFactory;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Tests\TestCase;

class TypeFactoryTest extends TestCase
{
    /**
     * Node factory.
     *
     * @var TypeFactory
     */
    protected $factory;

    /**
     * Setup test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->factory = new TypeFactory();
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
        $enumNode = PartialParser::enumTypeDefinition('
            enum Role {
                # Company administrator.
                ADMIN @enum(value:"admin")
    
                # Company employee.
                EMPLOYEE @enum(value:"employee")
            }
        ');

        $type = $this->factory->toType(new TypeValue($enumNode));

        $this->assertInstanceOf(EnumType::class, $type);
        $this->assertEquals('Role', $type->name);
    }

    /**
     * @test
     */
    public function itCanTransformScalars()
    {
        $scalarNode = PartialParser::scalarTypeDefinition('
            scalar DateTime @scalar(class:"DateTime")
        ');

        $type = $this->factory->toType(new TypeValue($scalarNode));

        $this->assertInstanceOf(ScalarType::class, $type);
        $this->assertEquals('DateTime', $type->name);
    }

    /**
     * @test
     */
    public function itCanTransformInterfaces()
    {
        $interface = PartialParser::interfaceTypeDefinition('
            interface Node {
                _id: ID!
            }
        ');

        $type = $this->factory->toType(new TypeValue($interface));

        $this->assertInstanceOf(InterfaceType::class, $type);
        $this->assertEquals('Node', $type->name);
        $this->assertArrayHasKey('_id', $type->config['fields']);
    }

    /**
     * @test
     */
    public function itCanTransformObjectTypes()
    {
        $objectTypeDefinition = PartialParser::objectTypeDefinition('
            type User {
                foo(bar: String! @bcrypt): String!
            }
        ');

        $type = $this->factory->toType(new TypeValue($objectTypeDefinition));

        $this->assertInstanceOf(ObjectType::class, $type);
        $this->assertEquals('User', $type->name);
        $this->assertArrayHasKey('foo', $type->config['fields']());
    }

    /**
     * @test
     */
    public function itCanTransformInputObjectTypes()
    {
        $input = PartialParser::inputObjectTypeDefinition('
            input UserInput {
                foo: String!
            }
        ');

        $type = $this->factory->toType(new TypeValue($input));

        $this->assertInstanceOf(InputObjectType::class, $type);
        $this->assertEquals('UserInput', $type->name);
        $this->assertArrayHasKey('foo', $type->config['fields']());
    }
}
