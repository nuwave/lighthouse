<?php

namespace Tests\Unit\Schema;

use Closure;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\UnionType;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\TestCase;

final class TypeRegistryTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    public function setUp(): void
    {
        parent::setUp();

        $this->typeRegistry = app(TypeRegistry::class);

        $astBuilder = app(ASTBuilder::class);
        $this->typeRegistry->setDocumentAST($astBuilder->documentAST());
    }

    public function testSetsEnumValueThroughDirective(): void
    {
        $enumNode = Parser::enumTypeDefinition(/** @lang GraphQL */ '
        enum Role {
            ADMIN @enum(value: 123)
        }
        ');
        /** @var \GraphQL\Type\Definition\EnumType $enumType */
        $enumType = $this->typeRegistry->handle($enumNode);

        $this->assertInstanceOf(EnumType::class, $enumType);
        $this->assertSame('Role', $enumType->name);

        $enumValueDefinition = $enumType->getValue('ADMIN');
        $this->assertInstanceOf(EnumValueDefinition::class, $enumValueDefinition);
        /** @var \GraphQL\Type\Definition\EnumValueDefinition $enumValueDefinition */
        $this->assertSame(123, $enumValueDefinition->value);
    }

    public function testDefaultsEnumValueToItsName(): void
    {
        $enumNode = Parser::enumTypeDefinition(/** @lang GraphQL */ '
        enum Role {
            EMPLOYEE
        }
        ');
        /** @var \GraphQL\Type\Definition\EnumType $enumType */
        $enumType = $this->typeRegistry->handle($enumNode);

        $this->assertInstanceOf(EnumType::class, $enumType);
        $this->assertSame('Role', $enumType->name);

        $enumValueDefinition = $enumType->getValue('EMPLOYEE');
        $this->assertInstanceOf(EnumValueDefinition::class, $enumValueDefinition);
        /** @var \GraphQL\Type\Definition\EnumValueDefinition $enumValueDefinition */
        $this->assertSame('EMPLOYEE', $enumValueDefinition->value);
    }

    public function testTransformScalars(): void
    {
        $scalarNode = Parser::scalarTypeDefinition(/** @lang GraphQL */ '
        scalar Email
        ');
        /** @var \GraphQL\Type\Definition\ScalarType $scalarType */
        $scalarType = $this->typeRegistry->handle($scalarNode);

        $this->assertInstanceOf(ScalarType::class, $scalarType);
        $this->assertSame('Email', $scalarType->name);
    }

    public function testPointToScalarClassThroughDirective(): void
    {
        $scalarNode = Parser::scalarTypeDefinition(/** @lang GraphQL */ '
        scalar DateTime @scalar(class: "Nuwave\\\Lighthouse\\\Schema\\\Types\\\Scalars\\\DateTime")
        ');
        /** @var \GraphQL\Type\Definition\ScalarType $scalarType */
        $scalarType = $this->typeRegistry->handle($scalarNode);

        $this->assertInstanceOf(ScalarType::class, $scalarType);
        $this->assertSame('DateTime', $scalarType->name);
    }

    public function testPointToScalarClassThroughDirectiveWithoutNamespace(): void
    {
        $scalarNode = Parser::scalarTypeDefinition(/** @lang GraphQL */ '
        scalar SomeEmail @scalar(class: "Email")
        ');
        /** @var \GraphQL\Type\Definition\ScalarType $scalarType */
        $scalarType = $this->typeRegistry->handle($scalarNode);

        $this->assertInstanceOf(ScalarType::class, $scalarType);
        $this->assertSame('SomeEmail', $scalarType->name);
    }

    public function testTransformInterfaces(): void
    {
        $interfaceNode = Parser::interfaceTypeDefinition(/** @lang GraphQL */ '
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
        $interfaceNode = Parser::interfaceTypeDefinition(/** @lang GraphQL */ '
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
        $interfaceNode = Parser::interfaceTypeDefinition(/** @lang GraphQL */ '
        interface Bar {
            bar: String
        }
        ');
        /** @var \GraphQL\Type\Definition\InterfaceType $interfaceType */
        $interfaceType = $this->typeRegistry->handle($interfaceNode);

        $this->assertInstanceOf(InterfaceType::class, $interfaceType);
        $this->assertSame('Bar', $interfaceType->name);
    }

    public function testTransformUnions(): void
    {
        $unionNode = Parser::unionTypeDefinition(/** @lang GraphQL */ '
        union Foo = Bar
        ');
        /** @var \GraphQL\Type\Definition\UnionType $unionType */
        $unionType = $this->typeRegistry->handle($unionNode);

        $this->assertInstanceOf(UnionType::class, $unionType);
        $this->assertSame('Foo', $unionType->name);
        $this->assertInstanceOf(Closure::class, $unionType->config['resolveType']);
    }

    public function testTransformObjectTypes(): void
    {
        $objectTypeNode = Parser::objectTypeDefinition(/** @lang GraphQL */ '
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

    public function testTransformInputObjectTypes(): void
    {
        $inputNode = Parser::inputObjectTypeDefinition(/** @lang GraphQL */ '
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
        $nonExistingTypeName = 'ThisTypeDoesNotExist';

        $this->expectExceptionObject(
            TypeRegistry::failedToLoadType($nonExistingTypeName)
        );
        $this->typeRegistry->get($nonExistingTypeName);
    }

    public function testDeterminesIfHasType(): void
    {
        $name = 'Foo';

        $this->assertFalse($this->typeRegistry->has($name));

        $type = new ObjectType(['name' => $name]);
        $this->typeRegistry->register($type);

        $this->assertTrue($this->typeRegistry->has($name));
    }

    public function testThrowsWhenRegisteringExistingType(): void
    {
        $name = 'Foo';
        $type = new ObjectType(['name' => $name]);
        $this->typeRegistry->register($type);

        $this->expectExceptionObject(
            TypeRegistry::triedToRegisterPresentType($name)
        );
        $this->typeRegistry->register($type);
    }

    public function testThrowsWhenRegisteringExistingTypeLazily(): void
    {
        $name = 'Foo';
        $makeType = static function () use ($name): ObjectType {
            return new ObjectType(['name' => $name]);
        };
        $this->typeRegistry->registerLazy($name, $makeType);

        $this->expectExceptionObject(
            TypeRegistry::triedToRegisterPresentType($name)
        );
        $this->typeRegistry->registerLazy($name, $makeType);
    }

    public function testOverwrite(): void
    {
        $name = 'Foo';

        $foo = new ObjectType(['name' => $name]);
        $this->typeRegistry->register($foo);

        $foo2 = new ObjectType(['name' => $name]);
        $this->typeRegistry->overwrite($foo2);

        $this->assertSame($foo2, $this->typeRegistry->get($name));
    }

    public function testOverwriteLazy(): void
    {
        $name = 'Foo';

        $type = new ObjectType(['name' => $name]);
        $makeType = static function () use ($type): ObjectType {
            return $type;
        };
        $this->typeRegistry->registerLazy($name, $makeType);

        $this->assertSame($type, $this->typeRegistry->get($name));

        $type2 = new ObjectType(['name' => $name]);
        $makeType2 = static function () use ($type2): ObjectType {
            return $type2;
        };
        $this->typeRegistry->overwriteLazy($name, $makeType2);

        $this->assertSame($type2, $this->typeRegistry->get($name));
    }

    public function testRegisterLazy(): void
    {
        $name = 'Foo';
        $foo = new ObjectType(['name' => $name]);
        $this->typeRegistry->registerLazy(
            $name,
            static function () use ($foo): ObjectType { return $foo; }
        );

        $this->assertSame($foo, $this->typeRegistry->get($name));
    }

    public function testPossibleTypes(): void
    {
        $documentTypeName = 'Foo';

        $this->schema = /** @lang GraphQL */ "
        type {$documentTypeName} {
            foo: ID
        }
        " . self::PLACEHOLDER_QUERY;

        app()->forgetInstance(ASTBuilder::class);
        $astBuilder = app(ASTBuilder::class);
        $this->typeRegistry->setDocumentAST($astBuilder->documentAST());

        $lazyTypeName = 'Bar';
        $this->typeRegistry->registerLazy(
            $lazyTypeName,
            static function () use ($lazyTypeName): ObjectType { return new ObjectType(['name' => $lazyTypeName]); }
        );

        $resolvedTypes = $this->typeRegistry->resolvedTypes();
        $this->assertArrayNotHasKey($documentTypeName, $resolvedTypes);
        $this->assertArrayNotHasKey($lazyTypeName, $resolvedTypes);

        $possibleTypes = $this->typeRegistry->possibleTypes();
        $this->assertArrayHasKey($documentTypeName, $possibleTypes);
        $this->assertArrayHasKey($lazyTypeName, $possibleTypes);

        $resolvedTypes = $this->typeRegistry->resolvedTypes();
        $this->assertArrayHasKey($documentTypeName, $resolvedTypes);
        $this->assertArrayHasKey($lazyTypeName, $resolvedTypes);
    }

    public function testPossibleTypesMaintainsSingletons(): void
    {
        $documentTypeName = 'Foo';

        $this->schema = /** @lang GraphQL */ "
        type {$documentTypeName} {
            foo: ID
        }
        " . self::PLACEHOLDER_QUERY;

        app()->forgetInstance(ASTBuilder::class);
        $astBuilder = app(ASTBuilder::class);
        $this->typeRegistry->setDocumentAST($astBuilder->documentAST());

        $lazyTypeName = 'Bar';
        $this->typeRegistry->registerLazy(
            $lazyTypeName,
            static function () use ($lazyTypeName): ObjectType { return new ObjectType(['name' => $lazyTypeName]); }
        );

        $documentType = $this->typeRegistry->get($documentTypeName);
        $lazyType = $this->typeRegistry->get($lazyTypeName);
        $possibleTypes = $this->typeRegistry->possibleTypes();

        $this->assertSame($documentType, $possibleTypes[$documentTypeName]);
        $this->assertSame($lazyType, $possibleTypes[$lazyTypeName]);
    }
}
