<?php

namespace Tests\Unit\Schema;

use Closure;
use Tests\TestCase;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\InterfaceType;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use GraphQL\Type\Definition\InputObjectType;
use Nuwave\Lighthouse\Schema\AST\PartialParser;

class TypeRegistryTest extends TestCase
{
    /**
     * The type registry.
     *
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeRegistry = app(TypeRegistry::class);
    }

    /**
     * @test
     */
    public function itSetsEnumValueThroughDirective(): void
    {
        $enumNode = PartialParser::enumTypeDefinition('
        enum Role {
            ADMIN @enum(value: 123)
        }
        ');
        /** @var \GraphQL\Type\Definition\EnumType $enumType */
        $enumType = $this->typeRegistry->handle($enumNode);

        $this->assertInstanceOf(EnumType::class, $enumType);
        $this->assertSame('Role', $enumType->name);
        $this->assertSame(123, $enumType->getValue('ADMIN')->value);
    }

    /**
     * @test
     */
    public function itDefaultsEnumValueToItsName(): void
    {
        $enumNode = PartialParser::enumTypeDefinition('
        enum Role {
            EMPLOYEE
        }
        ');
        /** @var \GraphQL\Type\Definition\EnumType $enumType */
        $enumType = $this->typeRegistry->handle($enumNode);

        $this->assertInstanceOf(EnumType::class, $enumType);
        $this->assertSame('Role', $enumType->name);
        $this->assertSame('EMPLOYEE', $enumType->getValue('EMPLOYEE')->value);
    }

    /**
     * @test
     */
    public function itCanTransformScalars(): void
    {
        $scalarNode = PartialParser::scalarTypeDefinition('
        scalar Email
        ');
        /** @var \GraphQL\Type\Definition\ScalarType $scalarType */
        $scalarType = $this->typeRegistry->handle($scalarNode);

        $this->assertInstanceOf(ScalarType::class, $scalarType);
        $this->assertSame('Email', $scalarType->name);
    }

    /**
     * @test
     */
    public function itCanPointToScalarClassThroughDirective(): void
    {
        $scalarNode = PartialParser::scalarTypeDefinition('
        scalar DateTime @scalar(class: "Nuwave\\\Lighthouse\\\Schema\\\Types\\\Scalars\\\DateTime")
        ');
        /** @var \GraphQL\Type\Definition\ScalarType $scalarType */
        $scalarType = $this->typeRegistry->handle($scalarNode);

        $this->assertInstanceOf(ScalarType::class, $scalarType);
        $this->assertSame('DateTime', $scalarType->name);
    }

    /**
     * @test
     */
    public function itCanPointToScalarClassThroughDirectiveWithoutNamespace(): void
    {
        $scalarNode = PartialParser::scalarTypeDefinition('
        scalar SomeEmail @scalar(class: "Email")
        ');
        /** @var \GraphQL\Type\Definition\ScalarType $scalarType */
        $scalarType = $this->typeRegistry->handle($scalarNode);

        $this->assertInstanceOf(ScalarType::class, $scalarType);
        $this->assertSame('SomeEmail', $scalarType->name);
    }

    /**
     * @test
     */
    public function itCanTransformInterfaces(): void
    {
        $interfaceNode = PartialParser::interfaceTypeDefinition('
        interface Foo {
            bar: String
        }
        ');
        /** @var \GraphQL\Type\Definition\InterfaceType $interfaceType */
        $interfaceType = $this->typeRegistry->handle($interfaceNode);

        $this->assertInstanceOf(InterfaceType::class, $interfaceType);
        $this->assertSame('Foo', $interfaceType->name);
        $this->assertArrayHasKey('bar', $interfaceType->getFields());
    }

    /**
     * @test
     */
    public function itResolvesInterfaceThoughNamespace(): void
    {
        $interfaceNode = PartialParser::interfaceTypeDefinition('
        interface Nameable {
            bar: String
        }
        ');
        /** @var \GraphQL\Type\Definition\InterfaceType $interfaceType */
        $interfaceType = $this->typeRegistry->handle($interfaceNode);

        $this->assertInstanceOf(InterfaceType::class, $interfaceType);
        $this->assertSame('Nameable', $interfaceType->name);
    }

    /**
     * @test
     */
    public function itResolvesInterfaceThoughSecondaryNamespace(): void
    {
        $interfaceNode = PartialParser::interfaceTypeDefinition('
        interface Bar {
            bar: String
        }
        ');
        /** @var \GraphQL\Type\Definition\InterfaceType $interfaceType */
        $interfaceType = $this->typeRegistry->handle($interfaceNode);

        $this->assertInstanceOf(InterfaceType::class, $interfaceType);
        $this->assertSame('Bar', $interfaceType->name);
    }

    /**
     * @test
     */
    public function itCanTransformUnions(): void
    {
        $unionNode = PartialParser::unionTypeDefinition('
        union Foo = Bar
        ');
        /** @var \GraphQL\Type\Definition\UnionType $unionType */
        $unionType = $this->typeRegistry->handle($unionNode);

        $this->assertInstanceOf(UnionType::class, $unionType);
        $this->assertSame('Foo', $unionType->name);
        $this->assertInstanceOf(Closure::class, $unionType->config['resolveType']);
    }

    /**
     * @test
     */
    public function itCanTransformObjectTypes(): void
    {
        $objectTypeNode = PartialParser::objectTypeDefinition('
        type User {
            foo(bar: String! @bcrypt): String!
        }
        ');
        /** @var \GraphQL\Type\Definition\ObjectType $objectType */
        $objectType = $this->typeRegistry->handle($objectTypeNode);

        $this->assertInstanceOf(ObjectType::class, $objectType);
        $this->assertSame('User', $objectType->name);
        $this->assertArrayHasKey('foo', $objectType->getFields());
    }

    /**
     * @test
     */
    public function itCanTransformInputObjectTypes(): void
    {
        $inputNode = PartialParser::inputObjectTypeDefinition('
        input UserInput {
            foo: String!
        }
        ');
        /** @var \GraphQL\Type\Definition\InputObjectType $inputObjectType */
        $inputObjectType = $this->typeRegistry->handle($inputNode);

        $this->assertInstanceOf(InputObjectType::class, $inputObjectType);
        $this->assertSame('UserInput', $inputObjectType->name);
        $this->assertArrayHasKey('foo', $inputObjectType->getFields());
    }
}
