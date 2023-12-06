<?php declare(strict_types=1);

namespace Tests\Unit\Schema;

use GraphQL\Language\Parser;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\TestCase;

final class TypeRegistryTest extends TestCase
{
    protected TypeRegistry $typeRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeRegistry = $this->app->make(TypeRegistry::class);

        $astBuilder = $this->app->make(ASTBuilder::class);
        assert($astBuilder instanceof ASTBuilder);

        $this->typeRegistry->setDocumentAST($astBuilder->documentAST());
    }

    public function testSetsEnumValueThroughDirective(): void
    {
        $enumNode = Parser::enumTypeDefinition(/** @lang GraphQL */ '
        enum Role {
            ADMIN @enum(value: 123)
        }
        ');

        $enumType = $this->typeRegistry->handle($enumNode);
        assert($enumType instanceof EnumType);

        $this->assertSame('Role', $enumType->name);

        $enumValueDefinition = $enumType->getValue('ADMIN');
        assert($enumValueDefinition instanceof EnumValueDefinition);

        $this->assertSame(123, $enumValueDefinition->value);
    }

    public function testDefaultsEnumValueToItsName(): void
    {
        $enumNode = Parser::enumTypeDefinition(/** @lang GraphQL */ '
        enum Role {
            EMPLOYEE
        }
        ');

        $enumType = $this->typeRegistry->handle($enumNode);
        assert($enumType instanceof EnumType);

        $this->assertSame('Role', $enumType->name);

        $enumValueDefinition = $enumType->getValue('EMPLOYEE');
        assert($enumValueDefinition instanceof EnumValueDefinition);

        $this->assertSame('EMPLOYEE', $enumValueDefinition->value);
    }

    public function testTransformScalars(): void
    {
        $scalarNode = Parser::scalarTypeDefinition(/** @lang GraphQL */ '
        scalar Email
        ');

        $scalarType = $this->typeRegistry->handle($scalarNode);
        assert($scalarType instanceof ScalarType);

        $this->assertSame('Email', $scalarType->name);
    }

    public function testPointToScalarClassThroughDirective(): void
    {
        $scalarNode = Parser::scalarTypeDefinition(/** @lang GraphQL */ '
        scalar DateTime @scalar(class: "Nuwave\\\Lighthouse\\\Schema\\\Types\\\Scalars\\\DateTime")
        ');

        $scalarType = $this->typeRegistry->handle($scalarNode);
        assert($scalarType instanceof ScalarType);

        $this->assertSame('DateTime', $scalarType->name);
    }

    public function testPointToScalarClassThroughDirectiveWithoutNamespace(): void
    {
        $scalarNode = Parser::scalarTypeDefinition(/** @lang GraphQL */ '
        scalar SomeEmail @scalar(class: "Email")
        ');

        $scalarType = $this->typeRegistry->handle($scalarNode);
        assert($scalarType instanceof ScalarType);

        $this->assertSame('SomeEmail', $scalarType->name);
    }

    public function testTransformInterfaces(): void
    {
        $bar = new InterfaceType([
            'name' => 'Bar',
            'fields' => [
                'bar' => Type::string(),
            ],
        ]);
        $this->typeRegistry->overwrite($bar);

        $interfaceNode = Parser::interfaceTypeDefinition(/** @lang GraphQL */ '
        interface Foo implements Bar {
            bar: String
        }
        ');

        $interfaceType = $this->typeRegistry->handle($interfaceNode);
        assert($interfaceType instanceof InterfaceType);

        $this->assertSame('Foo', $interfaceType->name);
        $this->assertArrayHasKey('bar', $interfaceType->getFields());
        $this->assertContains($bar, $interfaceType->getInterfaces());
    }

    public function testResolvesInterfaceThoughNamespace(): void
    {
        $interfaceNode = Parser::interfaceTypeDefinition(/** @lang GraphQL */ '
        interface Nameable {
            bar: String
        }
        ');

        $interfaceType = $this->typeRegistry->handle($interfaceNode);
        assert($interfaceType instanceof InterfaceType);

        $this->assertSame('Nameable', $interfaceType->name);
    }

    public function testResolvesInterfaceThoughSecondaryNamespace(): void
    {
        $interfaceNode = Parser::interfaceTypeDefinition(/** @lang GraphQL */ '
        interface Bar {
            bar: String
        }
        ');

        $interfaceType = $this->typeRegistry->handle($interfaceNode);
        assert($interfaceType instanceof InterfaceType);

        $this->assertSame('Bar', $interfaceType->name);
    }

    public function testTransformUnions(): void
    {
        $unionNode = Parser::unionTypeDefinition(/** @lang GraphQL */ '
        union Foo = Bar
        ');

        $unionType = $this->typeRegistry->handle($unionNode);
        assert($unionType instanceof UnionType);

        $this->assertSame('Foo', $unionType->name);
        $this->assertInstanceOf(\Closure::class, $unionType->config['resolveType'] ?? null);
    }

    public function testTransformObjectTypes(): void
    {
        $objectTypeNode = Parser::objectTypeDefinition(/** @lang GraphQL */ '
        type User {
            foo(bar: String! @hash): String!
        }
        ');

        $objectType = $this->typeRegistry->handle($objectTypeNode);
        assert($objectType instanceof ObjectType);

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

        $inputObjectType = $this->typeRegistry->handle($inputNode);
        assert($inputObjectType instanceof InputObjectType);

        $this->assertSame('UserInput', $inputObjectType->name);
        $this->assertArrayHasKey('foo', $inputObjectType->getFields());
    }

    public function testGetThrowsWhenMissingType(): void
    {
        $nonExistingTypeName = 'ThisTypeDoesNotExist';

        $this->expectExceptionObject(
            TypeRegistry::failedToLoadType($nonExistingTypeName),
        );
        $this->typeRegistry->get($nonExistingTypeName);
    }

    public function testSearchReturnsNullWhenMissingType(): void
    {
        $this->assertNull($this->typeRegistry->search('ThisTypeDoesNotExist'));
    }

    public function testDeterminesIfHasType(): void
    {
        $name = 'Foo';

        $this->assertFalse($this->typeRegistry->has($name));

        $type = new ObjectType([
            'name' => $name,
            'fields' => [],
        ]);
        $this->typeRegistry->register($type);

        $this->assertTrue($this->typeRegistry->has($name));
    }

    public function testThrowsWhenRegisteringExistingType(): void
    {
        $name = 'Foo';
        $type = new ObjectType([
            'name' => $name,
            'fields' => [],
        ]);
        $this->typeRegistry->register($type);

        $this->expectExceptionObject(
            TypeRegistry::triedToRegisterPresentType($name),
        );
        $this->typeRegistry->register($type);
    }

    public function testThrowsWhenRegisteringExistingTypeLazily(): void
    {
        $name = 'Foo';
        $makeType = static fn (): ObjectType => new ObjectType([
            'name' => $name,
            'fields' => [],
        ]);
        $this->typeRegistry->registerLazy($name, $makeType);

        $this->expectExceptionObject(
            TypeRegistry::triedToRegisterPresentType($name),
        );
        $this->typeRegistry->registerLazy($name, $makeType);
    }

    public function testOverwriteLazy(): void
    {
        $name = 'Foo';

        $type = new ObjectType([
            'name' => $name,
            'fields' => [],
        ]);
        $makeType = static fn (): ObjectType => $type;
        $this->typeRegistry->registerLazy($name, $makeType);

        $this->assertSame($type, $this->typeRegistry->get($name));

        $type2 = new ObjectType([
            'name' => $name,
            'fields' => [],
        ]);
        $makeType2 = static fn (): ObjectType => $type2;
        $this->typeRegistry->overwriteLazy($name, $makeType2);

        $this->assertSame($type2, $this->typeRegistry->get($name));
    }

    public function testRegisterLazy(): void
    {
        $name = 'Foo';
        $type = new ObjectType([
            'name' => $name,
            'fields' => [],
        ]);
        $this->typeRegistry->registerLazy(
            $name,
            static fn (): ObjectType => $type,
        );

        $this->assertSame($type, $this->typeRegistry->get($name));
    }

    public function testPossibleTypes(): void
    {
        $documentTypeName = 'Foo';

        $this->schema = /** @lang GraphQL */ "
        type {$documentTypeName} {
            foo: ID
        }
        " . self::PLACEHOLDER_QUERY;

        $this->app->forgetInstance(ASTBuilder::class);
        $astBuilder = $this->app->make(ASTBuilder::class);

        $this->typeRegistry->setDocumentAST($astBuilder->documentAST());

        $lazyTypeName = 'Bar';
        $this->typeRegistry->registerLazy(
            $lazyTypeName,
            static fn (): ObjectType => new ObjectType([
                'name' => $lazyTypeName,
                'fields' => [],
            ]),
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

        $this->app->forgetInstance(ASTBuilder::class);
        $astBuilder = $this->app->make(ASTBuilder::class);
        $this->typeRegistry->setDocumentAST($astBuilder->documentAST());

        $lazyTypeName = 'Bar';
        $this->typeRegistry->registerLazy(
            $lazyTypeName,
            static fn (): ObjectType => new ObjectType([
                'name' => $lazyTypeName,
                'fields' => [],
            ]),
        );

        $documentType = $this->typeRegistry->get($documentTypeName);
        $lazyType = $this->typeRegistry->get($lazyTypeName);
        $possibleTypes = $this->typeRegistry->possibleTypes();

        $this->assertSame($documentType, $possibleTypes[$documentTypeName]);
        $this->assertSame($lazyType, $possibleTypes[$lazyTypeName]);
    }
}
