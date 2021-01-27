<?php

namespace Tests\Unit\Schema;

use Closure;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\ResolverProvider;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Tests\TestCase;

class ResolverProviderTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\ResolverProvider
     */
    protected $resolverProvider;

    public function setUp(): void
    {
        parent::setUp();

        $this->resolverProvider = new ResolverProvider;
    }

    public function testGetsTheWebonyxDefaultResolverForNonRootFields(): void
    {
        $fieldValue = $this->constructFieldValue('nonExisting: Int', 'NonRoot');

        $this->assertInstanceOf(
            Closure::class,
            $this->resolverProvider->provideResolver($fieldValue)
        );
    }

    public function testGetsTheConventionBasedDefaultResolverForRootFields(): void
    {
        $fieldValue = $this->constructFieldValue('foo: Int');

        $this->assertInstanceOf(
            Closure::class,
            $this->resolverProvider->provideResolver($fieldValue)
        );
    }

    public function testLooksAtMultipleNamespacesWhenLookingForDefaultFieldResolvers(): void
    {
        $fieldValue = $this->constructFieldValue('baz: Int');

        $this->assertInstanceOf(
            Closure::class,
            $this->resolverProvider->provideResolver($fieldValue)
        );
    }

    public function testThrowsIfRootFieldHasNoResolver(): void
    {
        $this->expectException(DefinitionException::class);

        $this->resolverProvider->provideResolver(
            $this->constructFieldValue('noFieldClass: Int')
        );
    }

    protected function constructFieldValue(string $fieldDefinition, string $parentTypeName = RootType::QUERY): FieldValue
    {
        $queryType = Parser::objectTypeDefinition(/** @lang GraphQL */ "
        type {$parentTypeName} {
            {$fieldDefinition}
        }
        ");

        $typeValue = new TypeValue($queryType);

        return new FieldValue(
            $typeValue,
            $queryType->fields[0]
        );
    }
}
