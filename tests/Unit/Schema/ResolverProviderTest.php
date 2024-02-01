<?php declare(strict_types=1);

namespace Tests\Unit\Schema;

use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\ResolverProvider;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Tests\TestCase;

final class ResolverProviderTest extends TestCase
{
    protected ResolverProvider $resolverProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolverProvider = new ResolverProvider();
    }

    public function testGetsTheWebonyxDefaultResolverForNonRootFields(): void
    {
        $fieldValue = $this->constructFieldValue('nonExisting: Int', 'NonRoot');
        $this->resolverProvider->provideResolver($fieldValue);

        self::expectNotToPerformAssertions();
    }

    public function testGetsTheConventionBasedDefaultResolverForRootFields(): void
    {
        $fieldValue = $this->constructFieldValue('foo: Int');
        $this->resolverProvider->provideResolver($fieldValue);

        self::expectNotToPerformAssertions();
    }

    public function testLooksAtMultipleNamespacesWhenLookingForDefaultFieldResolvers(): void
    {
        $fieldValue = $this->constructFieldValue('baz: Int');
        $this->resolverProvider->provideResolver($fieldValue);

        self::expectNotToPerformAssertions();
    }

    public function testThrowsIfRootFieldHasNoResolver(): void
    {
        $fieldValue = $this->constructFieldValue('noFieldClass: Int');

        $this->expectException(DefinitionException::class);
        $this->resolverProvider->provideResolver($fieldValue);
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
            $queryType->fields[0],
        );
    }
}
