<?php

namespace Tests\Unit\Schema\Values;

use Closure;
use Tests\TestCase;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

class FieldValueTest extends TestCase
{
    /**
     * @test
     */
    public function itGetsTheWebonyxDefaultResolverForNonRootFields(): void
    {
        $fieldValue = $this->constructFieldValue('nonExisting: Int', 'NonRoot');

        $this->assertInstanceOf(
            Closure::class,
            $fieldValue->getResolver()
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
            $fieldValue->getResolver()
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
            $fieldValue->getResolver()
        );
    }

    /**
     * @test
     */
    public function itThrowsIfRootFieldHasNoResolver(): void
    {
        $this->expectException(DefinitionException::class);

        $this->constructFieldValue('noFieldClass: Int')->getResolver();
    }

    protected function constructFieldValue(string $fieldDefinition, string $parentTypeName = 'Query'): FieldValue
    {
        $queryType = PartialParser::objectTypeDefinition("
        type {$parentTypeName} {
            {$fieldDefinition}
        }
        ");

        $nodeValue = new NodeValue($queryType);

        return new FieldValue($nodeValue, $queryType->fields[0]);
    }
}
