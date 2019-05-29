<?php

namespace Tests\Unit\Schema;

use Closure;
use Tests\TestCase;
use Nuwave\Lighthouse\Schema\ResolverProvider;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

class ResolverProviderTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\ResolverProvider
     */
    private $resolverProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolverProvider = new ResolverProvider;
    }

    /**
     * @test
     */
    public function itGetsTheWebonyxDefaultResolverForNonRootFields(): void
    {
        $fieldValue = $this->constructFieldValue('nonExisting: Int', 'NonRoot');

        $this->assertInstanceOf(
            Closure::class,
            $this->resolverProvider->provideResolver($fieldValue)
        );
    }

    /**
     * @test
     */
    public function itGetsTheConventionBasedDefaultResolverForRootFields(): void
    {
        $fieldValue = $this->constructFieldValue('foo: Int');

        $this->assertInstanceOf(
            Closure::class,
            $this->resolverProvider->provideResolver($fieldValue)
        );
    }

    /**
     * @test
     */
    public function itLooksAtMultipleNamespacesWhenLookingForDefaultFieldResolvers(): void
    {
        $fieldValue = $this->constructFieldValue('baz: Int');

        $this->assertInstanceOf(
            Closure::class,
            $this->resolverProvider->provideResolver($fieldValue)
        );
    }

    /**
     * @test
     */
    public function itThrowsIfRootFieldHasNoResolver(): void
    {
        $this->expectException(DefinitionException::class);

        $this->resolverProvider->provideResolver(
            $this->constructFieldValue('noFieldClass: Int')
        );
    }

    protected function constructFieldValue(string $fieldDefinition, string $parentTypeName = 'Query'): FieldValue
    {
        $queryType = PartialParser::objectTypeDefinition("
        type {$parentTypeName} {
            {$fieldDefinition}
        }
        ");

        $typeValue = new TypeValue($queryType);

        return new FieldValue($typeValue, $queryType->fields[0]);
    }
}
