<?php

namespace Tests\Unit\Schema\Factories;

use Tests\TestCase;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\InputObjectType;
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

    protected function setUp()
    {
        parent::setUp();

        $this->factory = app(NodeFactory::class);
    }

    /**
     * @test
     */
    public function itSetsEnumValueThroughDirective()
    {
        $enumNode = PartialParser::enumTypeDefinition('
        enum Role {
            ADMIN @enum(value: 123)
        }
        ');
        /** @var EnumType $type */
        $type = $this->factory->handle($enumNode);

        $this->assertInstanceOf(EnumType::class, $type);
        $this->assertSame('Role', $type->name);
        $this->assertSame(123, $type->getValue('ADMIN')->value);
    }

    /**
     * @test
     */
    public function itDefaultsEnumValueToItsName()
    {
        $enumNode = PartialParser::enumTypeDefinition('
        enum Role {
            EMPLOYEE
        }
        ');
        /** @var EnumType $type */
        $type = $this->factory->handle($enumNode);

        $this->assertInstanceOf(EnumType::class, $type);
        $this->assertSame('Role', $type->name);
        $this->assertSame('EMPLOYEE', $type->getValue('EMPLOYEE')->value);
    }

    /**
     * @test
     */
    public function itCanTransformScalars()
    {
        $scalarNode = PartialParser::scalarTypeDefinition('
        scalar Email
        ');
        $scalarType = $this->factory->handle($scalarNode);

        $this->assertInstanceOf(ScalarType::class, $scalarType);
        $this->assertSame('Email', $scalarType->name);
    }

    /**
     * @test
     */
    public function itCanPointToScalarClassThroughDirective()
    {
        $scalarNode = PartialParser::scalarTypeDefinition('
        scalar DateTime @scalar(class: "Nuwave\\\Lighthouse\\\Schema\\\Types\\\Scalars\\\DateTime")
        ');
        $scalarType = $this->factory->handle($scalarNode);

        $this->assertInstanceOf(ScalarType::class, $scalarType);
        $this->assertSame('DateTime', $scalarType->name);
    }

    /**
     * @test
     */
    public function itCanPointToScalarClassThroughDirectiveWithoutNamespace()
    {
        $scalarNode = PartialParser::scalarTypeDefinition('
        scalar SomeEmail @scalar(class: "Email")
        ');
        $scalarType = $this->factory->handle($scalarNode);

        $this->assertInstanceOf(ScalarType::class, $scalarType);
        $this->assertSame('SomeEmail', $scalarType->name);
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
        $interfaceType = $this->factory->handle($interfaceNode);

        $this->assertInstanceOf(InterfaceType::class, $interfaceType);
        $this->assertSame('Foo', $interfaceType->name);
        $this->assertArrayHasKey('bar', $interfaceType->getFields());
    }

    /**
     * @test
     */
    public function itResolvesInterfaceThoughNamespace()
    {
        $interfaceNode = PartialParser::interfaceTypeDefinition('
        interface Nameable {
            bar: String
        }
        ');
        $interfaceType = $this->factory->handle($interfaceNode);

        $this->assertInstanceOf(InterfaceType::class, $interfaceType);
        $this->assertSame('Nameable', $interfaceType->name);
    }

    /**
     * @test
     */
    public function itResolvesInterfaceThoughSecondaryNamespace()
    {
        $interfaceNode = PartialParser::interfaceTypeDefinition('
        interface Bar {
            bar: String
        }
        ');
        $interfaceType = $this->factory->handle($interfaceNode);

        $this->assertInstanceOf(InterfaceType::class, $interfaceType);
        $this->assertSame('Bar', $interfaceType->name);
    }

    /**
     * @test
     */
    public function itCanTransformUnions()
    {
        $unionNode = PartialParser::unionTypeDefinition('
        union Foo = Bar
        ');
        $unionType = $this->factory->handle($unionNode);

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
        $objectType = $this->factory->handle($objectTypeNode);

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
        $inputType = $this->factory->handle($inputNode);

        $this->assertInstanceOf(InputObjectType::class, $inputType);
        $this->assertSame('UserInput', $inputType->name);
        $this->assertArrayHasKey('foo', $inputType->getFields());
    }
}
