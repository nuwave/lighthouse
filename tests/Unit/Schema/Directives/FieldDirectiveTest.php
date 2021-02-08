<?php

namespace Tests\Unit\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\TestCase;
use Tests\Utils\Queries\FooBar;

class FieldDirectiveTest extends TestCase
{
    public function testAssignsResolverFromCombinedDefinition(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            bar: String! @field(resolver:"Tests\\\Utils\\\Resolvers\\\Foo@bar")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            bar
        }
        ')->assertJson([
            'data' => [
                'bar' => 'foo.bar',
            ],
        ]);
    }

    public function testAssignsResolverWithInvokableClass(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            baz: String! @field(resolver:"Tests\\\Utils\\\Resolvers\\\Foo")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            baz
        }
        ')->assertJson([
            'data' => [
                'baz' => 'foo.baz',
            ],
        ]);
    }

    public function testCanResolveFieldWithMergedArgs(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            bar: String! @field(resolver: "Tests\\\Utils\\\Resolvers\\\Foo@baz" args: ["foo.baz"])
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            bar
        }
        ')->assertJson([
            'data' => [
                'bar' => 'foo.baz',
            ],
        ]);
    }

    public function testUsesDefaultFieldNamespace(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            bar: String! @field(resolver: "FooBar@customResolve")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            bar
        }
        ')->assertJson([
            'data' => [
                'bar' => FooBar::CUSTOM_RESOLVE_RESULT,
            ],
        ]);
    }

    public function testUsesDefaultFieldNamespaceForInvokableClass(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            baz: String! @field(resolver: "FooBar")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            baz
        }
        ')->assertJson([
            'data' => [
                'baz' => FooBar::INVOKE_RESULT,
            ],
        ]);
    }

    public function testThrowsAnErrorWhenNoClassFound(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('No class `NonExisting` was found for directive `@field`');

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: String! @field(resolver: "NonExisting")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }

    public function testThrowsAnErrorWhenClassIsNotInvokable(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage("Method '__invoke' does not exist on class 'Tests\Utils\Queries\MissingInvoke'");

        $this->schema = /** @lang GraphQL */ '
        type Query {
            bar: String! @field(resolver: "MissingInvoke")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            bar
        }
        ');
    }
}
