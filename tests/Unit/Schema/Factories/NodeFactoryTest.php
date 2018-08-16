<?php

namespace Tests\Unit\Schema\Factories;

use Tests\TestCase;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\InputObjectType;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Factories\NodeFactory;

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
        $enumNode = PartialParser::enumTypeDefinition('
        enum Role {
            "Company administrator."
            ADMIN @enum(value:"admin")

            "Company employee."
            EMPLOYEE @enum(value:"employee")
        }
        ');
        $type = $this->factory->handle(new NodeValue($enumNode));

        $this->assertInstanceOf(EnumType::class, $type);
        $this->assertEquals('Role', $type->name);
    }

    /**
     * @test
     */
    public function itCanTransformScalars()
    {
        $scalarNode = PartialParser::scalarTypeDefinition('
        scalar DateTime @scalar
        ');
        $scalarType = $this->factory->handle(new NodeValue($scalarNode));

        $this->assertInstanceOf(ScalarType::class, $scalarType);
        $this->assertEquals('DateTime', $scalarType->name);
    }

    /**
     * @test
     */
    public function itCanTransformInterfaces()
    {
        $interfaceNode = PartialParser::interfaceTypeDefinition('
        interface Node {
            _id: ID!
        }
        ');
        $interfaceType = $this->factory->handle(new NodeValue($interfaceNode));

        $this->assertInstanceOf(InterfaceType::class, $interfaceType);
        $this->assertEquals('Node', $interfaceType->name);
        $this->assertArrayHasKey('_id', $interfaceType->config['fields']);
    }

    /**
     * @test
     */
    public function itCanTransformObjectTypes()
    {
        $objectTypeNode = PartialParser::objectTypeDefinition('
        type User {
            foo(bar: String! @bcrypt): String!
        }
        ');
        $objectType = $this->factory->handle(new NodeValue($objectTypeNode));

        $this->assertInstanceOf(ObjectType::class, $objectType);
        $this->assertEquals('User', $objectType->name);
        $this->assertArrayHasKey('foo', $objectType->config['fields']());
    }

    /**
     * @test
     */
    public function itCanTransformInputObjectTypes()
    {
        $inputNode = PartialParser::inputObjectTypeDefinition('
        input UserInput {
            foo: String!
        }
        ');
        $inputType = $this->factory->handle(new NodeValue($inputNode));

        $this->assertInstanceOf(InputObjectType::class, $inputType);
        $this->assertEquals('UserInput', $inputType->name);
        $this->assertArrayHasKey('foo', $inputType->config['fields']());
    }
}
