<?php

namespace Tests\Unit\Schema;

use Closure;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\ResolverProvider;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Tests\TestCase;
use Tests\Utils\Queries\FooBar;

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

    public function testGetsTheConventionBasedDefaultResolverForRootFieldsWithInvoke(): void
    {
        $fieldValue = $this->constructFieldValue('fooInvoke: Int');

        $this->assertInstanceOf(
            Closure::class,
            $this->resolverProvider->provideResolver($fieldValue)
        );
    }

    /**
     * @deprecated will be changed in v5
     */
    public function testGetsTheConventionBasedDefaultResolverForRootFieldsAndDefaultsToResolve(): void
    {
        $fieldValue = $this->constructFieldValue('fooBar: String');

        $resolver = $this->resolverProvider->provideResolver($fieldValue);
        $this->assertInstanceOf(
            Closure::class,
            $resolver
        );

        $this->assertSame(
            FooBar::RESOLVE_RESULT,
            $resolver()
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
