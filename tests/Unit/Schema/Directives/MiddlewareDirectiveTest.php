<?php

namespace Tests\Unit\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Tests\TestCase;
use Tests\Utils\Middleware\Authenticate;
use Tests\Utils\Middleware\CountRuns;

/**
 * @deprecated The @middleware directive will be removed in v5
 */
class MiddlewareDirectiveTest extends TestCase
{
    /**
     * @dataProvider fooMiddlewareQueries
     *
     * @param  string  $query
     * @return void
     */
    public function testCallsFooMiddleware(string $query): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int
                @middleware(checks: ["Tests\\\Utils\\\Middleware\\\CountRuns"])
                @field(resolver: "Tests\\\Utils\\\Middleware\\\CountRuns@resolve")
        }
        ';

        $this->graphQL($query)->assertJson([
            'data' => [
                'foo' => 1,
            ],
        ]);
    }

    /**
     * @return array[]
     */
    public function fooMiddlewareQueries(): array
    {
        return [
            [/** @lang GraphQL */ '
            {
                foo
            }
            '],
            [/** @lang GraphQL */ '
            query FooQuery {
                ...Foo_Fragment
            }

            fragment Foo_Fragment on Query {
                foo
            }
            '],
        ];
    }

    public function testWrapsExceptionFromMiddlewareInResponse(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            bar: String
            foo: Int @middleware(checks: ["Tests\\\Utils\\\Middleware\\\Authenticate"])
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
            bar
        }
        ')->assertJson([
            'errors' => [
                [
                    'message' => Authenticate::MESSAGE,
                ],
            ],
        ]);
    }

    public function testRunsAliasedMiddleware(): void
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('foo', CountRuns::class);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int
                @middleware(checks: ["foo"])
                @field(resolver: "Tests\\\Utils\\\Middleware\\\CountRuns@resolve")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => 1,
            ],
        ]);
    }

    public function testRunsMiddlewareGroup(): void
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app['router'];
        $router->middlewareGroup('bar', [Authenticate::class]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int
                @middleware(checks: ["bar"])
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'errors' => [
                [
                    'message' => Authenticate::MESSAGE,
                ],
            ],
        ]);
    }

    public function testPassesOneFieldButThrowsInAnother(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int
                @middleware(checks: ["Tests\\\Utils\\\Middleware\\\Authenticate"])
            pass: Int
                @middleware(checks: ["Tests\\\Utils\\\Middleware\\\CountRuns"])
                @field(resolver: "Tests\\\Utils\\\Middleware\\\CountRuns@resolve")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
            pass
        }
        ')->assertJson([
            'data' => [
                'foo' => null,
                'pass' => 1,
            ],
            'errors' => [
                [
                    'path' => [
                        'foo',
                    ],
                    'message' => Authenticate::MESSAGE,
                ],
            ],
        ]);
    }

    public function testThrowsWhenDefiningMiddlewareOnInvalidTypes(): void
    {
        $this->expectException(DefinitionException::class);
        $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        scalar Foo @middleware
        ');
    }

    public function testAddsMiddlewareDirectiveToFields(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query @middleware(checks: ["auth", "Tests\\\Utils\\\Middleware\\\Authenticate", "api"]) {
            foo: Int
        }
        ';

        /** @var \Nuwave\Lighthouse\Schema\AST\ASTBuilder $astBuilder */
        $astBuilder = app(ASTBuilder::class);
        $document = $astBuilder->documentAST();

        $queryType = $document->types['Query'];

        $middlewareOnFooArguments = $queryType->fields[0]->directives[0];
        $fieldMiddlewares = ASTHelper::directiveArgValue($middlewareOnFooArguments, 'checks');

        $this->assertSame(
            [
                'auth',
                Authenticate::class,
                'api',
            ],
            $fieldMiddlewares
        );
    }

    public function testPrefersFieldMiddlewareOverTypeMiddleware(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query @middleware(checks: ["auth"]) {
            foo: Int @middleware(checks: ["api"])
        }
        ';

        /** @var \Nuwave\Lighthouse\Schema\AST\ASTBuilder $astBuilder */
        $astBuilder = app(ASTBuilder::class);
        $document = $astBuilder->documentAST();

        $queryType = $document->types['Query'];

        $middlewareOnFooArguments = $queryType->fields[0]->directives[0];
        $fieldMiddlewares = ASTHelper::directiveArgValue($middlewareOnFooArguments, 'checks');

        $this->assertSame(
            [
                'api',
            ],
            $fieldMiddlewares
        );
    }
}
