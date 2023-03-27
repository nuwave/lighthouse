<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\TestCase;
use Tests\Utils\Queries\FooBar;
use Tests\Utils\Types\User\NonRootClassResolver;

final class FieldDirectiveTest extends TestCase
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

    public function testUsesDefaultFieldNamespace(): void
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

    public function testUsesNonRootParentNamespace(): void
    {
        $this->mockResolver([]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User @mock
        }

        type User {
            foo: String! @field(resolver: "NonRootClassResolver")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                foo
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'foo' => NonRootClassResolver::RESULT,
                ],
            ],
        ]);
    }

    public function testUsesDefaultFieldNamespaceWithCustomMethodName(): void
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

    public function testThrowsAnErrorWhenNoClassFound(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: String! @field(resolver: "NonExisting")
        }
        ';

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('Failed to find class NonExisting in namespaces [Tests\Utils\Queries, Tests\Utils\QueriesSecondary] for directive @field.');
        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }

    public function testThrowsAnErrorWhenClassIsNotInvokable(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            bar: String! @field(resolver: "MissingInvoke")
        }
        ';

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage("Method '__invoke' does not exist on class 'Tests\Utils\Queries\MissingInvoke'.");
        $this->graphQL(/** @lang GraphQL */ '
        {
            bar
        }
        ');
    }
}
