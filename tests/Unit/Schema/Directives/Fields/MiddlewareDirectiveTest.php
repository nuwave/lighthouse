<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Illuminate\Routing\Router;
use Tests\Utils\Middleware\CountRuns;
use Tests\Utils\Middleware\Authenticate;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class MiddlewareDirectiveTest extends TestCase
{
    /**
     * @test
     * @dataProvider fooMiddlewareQueries
     *
     * @param  string  $query
     */
    public function itCallsFooMiddleware(string $query): void
    {
        $this->schema = '
        type Query {
            foo: Int
                @middleware(checks: ["Tests\\\Utils\\\Middleware\\\CountRuns"])
                @field(resolver: "Tests\\\Utils\\\Middleware\\\CountRuns@resolve")
        }
        ';

        $this->query($query)->assertJson([
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
            ['
            {
                foo
            }
            '],
            ['
            query FooQuery {
                ...Foo_Fragment
            }
            
            fragment Foo_Fragment on Query {
                foo
            }
            '],
        ];
    }

    /**
     * @test
     */
    public function itWrapsExceptionFromMiddlewareInResponse(): void
    {
        $this->schema = '
        type Query {
            foo: Int @middleware(checks: ["Tests\\\Utils\\\Middleware\\\Authenticate"])
        }
        ';

        $this->query('
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

    /**
     * @test
     */
    public function itRunsAliasedMiddleware(): void
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('foo', CountRuns::class);

        $this->schema = '
        type Query {
            foo: Int
                @middleware(checks: ["foo"])
                @field(resolver: "Tests\\\Utils\\\Middleware\\\CountRuns@resolve")
        }
        ';

        $this->query('
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => 1,
            ],
        ]);
    }

    /**
     * @test
     */
    public function itRunsMiddlewareGroup(): void
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app['router'];
        $router->middlewareGroup('bar', [Authenticate::class]);

        $this->schema = '
        type Query {
            foo: Int
                @middleware(checks: ["bar"])
        }
        ';

        $this->query('
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

    /**
     * @test
     */
    public function itPassesOneFieldButThrowsInAnother(): void
    {
        $this->schema = '
        type Query {
            foo: Int
                @middleware(checks: ["Tests\\\Utils\\\Middleware\\\Authenticate"])
            pass: Int
                @middleware(checks: ["Tests\\\Utils\\\Middleware\\\CountRuns"])
                @field(resolver: "Tests\\\Utils\\\Middleware\\\CountRuns@resolve")
        }
        ';

        $this->query('
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

    /**
     * @test
     */
    public function itThrowsWhenDefiningMiddlewareOnInvalidTypes(): void
    {
        $this->expectException(DirectiveException::class);
        $this->buildSchemaWithPlaceholderQuery('
        scalar Foo @middleware
        ');
    }

    /**
     * @test
     */
    public function itAddsMiddlewareDirectiveToFields(): void
    {
        $document = ASTBuilder::generate('
        type Query @middleware(checks: ["auth", "Tests\\\Utils\\\Middleware\\\Authenticate", "api"]) {
            foo: Int
        } 
        ');

        $queryType = $document->queryTypeDefinition();

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
}
