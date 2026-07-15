<?php declare(strict_types=1);

namespace Tests\Unit\Schema;

use Nuwave\Lighthouse\Schema\RootType;
use Tests\TestCase;

final class RootTypeTest extends TestCase
{
    public function testNamespacesReturnsConfiguredQueryNamespaces(): void
    {
        config()->set('lighthouse.namespaces.queries', ['App\\GraphQL\\Queries']);

        $namespaces = RootType::namespaces(RootType::QUERY);

        self::assertSame(['App\\GraphQL\\Queries'], $namespaces);
    }

    public function testNamespacesReturnsConfiguredMutationNamespaces(): void
    {
        config()->set('lighthouse.namespaces.mutations', ['App\\GraphQL\\Mutations', 'App\\Custom\\Mutations']);

        $namespaces = RootType::namespaces(RootType::MUTATION);

        self::assertSame(['App\\GraphQL\\Mutations', 'App\\Custom\\Mutations'], $namespaces);
    }

    public function testNamespacesThrowsForInvalidRootType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid root type: Invalid.');

        RootType::namespaces('Invalid');
    }

    public function testNamespacesThrowsWhenConfigIsEmpty(): void
    {
        config()->set('lighthouse.namespaces.queries', []);

        $this->expectException(\RuntimeException::class);

        RootType::namespaces(RootType::QUERY);
    }

    public function testNamespacesThrowsWhenConfigIsNull(): void
    {
        config()->set('lighthouse.namespaces.queries', null);

        $this->expectException(\RuntimeException::class);

        RootType::namespaces(RootType::QUERY);
    }

    public function testNamespacesThrowsForNonStringValues(): void
    {
        config()->set('lighthouse.namespaces.queries', [123, true]);

        $this->expectException(\RuntimeException::class);

        RootType::namespaces(RootType::QUERY);
    }

    public function testNamespacesWrapsStringConfigInArray(): void
    {
        config()->set('lighthouse.namespaces.queries', 'App\\GraphQL\\Queries');

        $namespaces = RootType::namespaces(RootType::QUERY);

        self::assertSame(['App\\GraphQL\\Queries'], $namespaces);
    }
}
