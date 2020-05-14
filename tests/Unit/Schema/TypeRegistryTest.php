<?php

namespace Tests\Unit\Schema;

use Closure;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\UnionType;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\TestCase;

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

    public function testSetsEnumValueThroughDirective(): void
    {
        $enumNode = PartialParser::enumTypeDefinition(/** @lang GraphQL */ '
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

    public function testDefaultsEnumValueToItsName(): void
    {
        $enumNode = PartialParser::enumTypeDefinition(/** @lang GraphQL */ '
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

    public function testCanTransformScalars(): void
    {
        $scalarNode = PartialParser::scalarTypeDefinition(/** @lang GraphQL */ '
        scalar Email
        ');
        /** @var \GraphQL\Type\Definition\ScalarType $scalarType */
        $scalarType = $this->typeRegistry->handle($scalarNode);

        $this->assertInstanceOf(ScalarType::class, $scalarType);
        $this->assertSame('Email', $scalarType->name);
    }

    public function testCanPointToScalarClassThroughDirective(): void
    {
        $scalarNode = PartialParser::scalarTypeDefinition(/** @lang GraphQL */ '
        scalar DateTime @scalar(class: "Nuwave\\\Lighthouse\\\Schema\\\Types\\\Scalars\\\DateTime")
        ');
        /** @var \GraphQL\Type\Definition\ScalarType $scalarType */
        $scalarType = $this->typeRegistry->handle($scalarNode);

        $this->assertInstanceOf(ScalarType::class, $scalarType);
        $this->assertSame('DateTime', $scalarType->name);
    }

    public function testCanPointToScalarClassThroughDirectiveWithoutNamespace(): void
    {
        $scalarNode = PartialParser::scalarTypeDefinition(/** @lang GraphQL */ '
        scalar SomeEmail @scalar(class: "Email")
        ');
        /** @var \GraphQL\Type\Definition\ScalarType $scalarType */
        $scalarType = $this->typeRegistry->handle($scalarNode);

        $this->assertInstanceOf(ScalarType::class, $scalarType);
        $this->assertSame('SomeEmail', $scalarType->name);
    }

    public function testCanTransformInterfaces(): void
    {
        $interfaceNode = PartialParser::interfaceTypeDefinition(/** @lang GraphQL */ '
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

    public function testResolvesInterfaceThoughNamespace(): void
    {
        $interfaceNode = PartialParser::interfaceTypeDefinition(/** @lang GraphQL */ '
        interface Nameable {
            bar: String
        }
        ');
        /** @var \GraphQL\Type\Definition\InterfaceType $interfaceType */
        $interfaceType = $this->typeRegistry->handle($interfaceNode);

        $this->assertInstanceOf(InterfaceType::class, $interfaceType);
        $this->assertSame('Nameable', $interfaceType->name);
    }

    public function testResolvesInterfaceThoughSecondaryNamespace(): void
    {
        $interfaceNode = PartialParser::interfaceTypeDefinition(/** @lang GraphQL */ '
        interface Bar {
            bar: String
        }
        ');
        /** @var \GraphQL\Type\Definition\InterfaceType $interfaceType */
        $interfaceType = $this->typeRegistry->handle($interfaceNode);

        $this->assertInstanceOf(InterfaceType::class, $interfaceType);
        $this->assertSame('Bar', $interfaceType->name);
    }

    public function testCanTransformUnions(): void
    {
        $unionNode = PartialParser::unionTypeDefinition(/** @lang GraphQL */ '
        union Foo = Bar
        ');
        /** @var \GraphQL\Type\Definition\UnionType $unionType */
        $unionType = $this->typeRegistry->handle($unionNode);

        $this->assertInstanceOf(UnionType::class, $unionType);
        $this->assertSame('Foo', $unionType->name);
        $this->assertInstanceOf(Closure::class, $unionType->config['resolveType']);
    }

    public function testCanTransformObjectTypes(): void
    {
        $objectTypeNode = PartialParser::objectTypeDefinition(/** @lang GraphQL */ '
        type User {
            foo(bar: String! @hash): String!
        }
        ');
        /** @var \GraphQL\Type\Definition\ObjectType $objectType */
        $objectType = $this->typeRegistry->handle($objectTypeNode);

        $this->assertInstanceOf(ObjectType::class, $objectType);
        $this->assertSame('User', $objectType->name);
        $this->assertArrayHasKey('foo', $objectType->getFields());
    }

    public function testCanTransformInputObjectTypes(): void
    {
        $inputNode = PartialParser::inputObjectTypeDefinition(/** @lang GraphQL */ '
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

    public function testGetThrowsWhenMissingType(): void
    {
        $this->expectException(DefinitionException::class);
        $this->typeRegistry->get('ThisTypeDoesNotExist');
    }

    public function testDeterminesIfHasType(): void
    {
        $fooName = 'Foo';
        $this->assertFalse($this->typeRegistry->has($fooName));

        $foo = new ObjectType(['name' => $fooName]);
        $this->typeRegistry->register($foo);
        $this->assertTrue($this->typeRegistry->has($fooName));
    }

    public function testThrowsWhenRegisteringExistingType(): void
    {
        $foo = new ObjectType(['name' => 'Foo']);
        $this->typeRegistry->registerNew($foo);

        $this->expectException(DefinitionException::class);
        $this->typeRegistry->registerNew($foo);
    }
}
