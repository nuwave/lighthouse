<?php

namespace Tests\Unit\Schema\Factories;

use Tests\TestCase;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\UnionType;
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

        $this->factory = resolve(NodeFactory::class);
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
        $this->assertSame('Role', $type->name);
    }

    /**
     * @test
     */
    public function itCanTransformScalars()
    {
        $scalarNode = PartialParser::scalarTypeDefinition('
        scalar Email
        ');
        $scalarType = $this->factory->handle(new NodeValue($scalarNode));

        $this->assertInstanceOf(ScalarType::class, $scalarType);
        $this->assertSame('Email', $scalarType->name);
    }

    /**
     * @test
     */
    public function itCanTransformInterfaces()
    {
        $interfaceNode = PartialParser::interfaceTypeDefinition('
        interface Foo {
            bar: String
        }
        ');
        $interfaceType = $this->factory->handle(new NodeValue($interfaceNode));

        $this->assertInstanceOf(InterfaceType::class, $interfaceType);
        $this->assertSame('Foo', $interfaceType->name);
        $this->assertArrayHasKey('bar', $interfaceType->getFields());
    }
    
    /**
     * @test
     */
    public function itCanTransformUnions()
    {
        $unionNode = PartialParser::unionTypeDefinition('
        union Foo = Bar
        ');
        $unionType = $this->factory->handle(new NodeValue($unionNode));

        $this->assertInstanceOf(UnionType::class, $unionType);
        $this->assertSame('Foo', $unionType->name);
        $this->assertInstanceOf(\Closure::class, $unionType->config['resolveType']);
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
        $this->assertSame('User', $objectType->name);
        $this->assertArrayHasKey('foo', $objectType->getFields());
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
        $this->assertSame('UserInput', $inputType->name);
        $this->assertArrayHasKey('foo', $inputType->getFields());
    }
}
