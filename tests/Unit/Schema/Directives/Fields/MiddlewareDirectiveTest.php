<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Illuminate\Routing\Router;
use Orchestra\Testbench\Http\Kernel;
use Tests\TestCase;
use Nuwave\Lighthouse\Schema\Context;
use Tests\Utils\Middleware\Authenticate;
use Tests\Utils\Middleware\AddFooProperty;

class MiddlewareDirectiveTest extends TestCase
{
    /**
     * @test
     * @dataProvider fooMiddlewareQueries
     *
     * @param string $query
     */
    public function itCallsFooMiddleware(string $query)
    {
        $this->schema = '
        type Query {
            foo: String
                @middleware(checks: ["Tests\\\Utils\\\Middleware\\\AddFooProperty"])
                @field(resolver: "Tests\\\Utils\\\Middleware\\\AddFooProperty@resolve")
        }
        ';

        $result = $this->queryViaHttp($query);

        $this->assertSame(AddFooProperty::DID_RUN, array_get($result, 'data.foo'));
    }

    public function fooMiddlewareQueries()
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
            ']
        ];
    }

    /**
     * @test
     */
    public function itWrapsExceptionFromMiddlewareInResponse()
    {
        $this->schema = '
        type Query {
            foo: Int @middleware(checks: ["Tests\\\Utils\\\Middleware\\\Authenticate"])
        }
        ';

        $result = $this->queryViaHttp('
        {
            foo
        }
        ');

        $this->assertSame(Authenticate::MESSAGE, array_get($result, 'errors.0.message'));
    }

    /**
     * @test
     */
    public function itRunsAliasedMiddleware()
    {
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('foo', AddFooProperty::class);

        $this->schema = '
        type Query {
            foo: String
                @middleware(checks: ["foo"])
                @field(resolver: "Tests\\\Utils\\\Middleware\\\AddFooProperty@resolve")
        }
        ';

        $result = $this->queryViaHttp('
        {
            foo
        }
        ');

        $this->assertSame(AddFooProperty::DID_RUN, array_get($result, 'data.foo'));
    }

    /**
     * @test
     */
    public function itRunsMiddlewareGroup()
    {
        /** @var Router $router */
        $router = $this->app['router'];
        $router->middlewareGroup('bar', [Authenticate::class]);

        $this->schema = '
        type Query {
            foo: Int
                @middleware(checks: ["bar"])
        }
        ';

        $result = $this->queryViaHttp('
        {
            foo
        }
        ');

        $this->assertSame(Authenticate::MESSAGE, array_get($result, 'errors.0.message'));
    }

    /**
     * @test
     */
    public function itPassesOneFieldButThrowsInAnother()
    {
        $this->schema = '
        type Query {
            fail: Int @middleware(checks: ["Tests\\\Utils\\\Middleware\\\Authenticate"])
            pass: String
                @middleware(checks: ["Tests\\\Utils\\\Middleware\\\AddFooProperty"])
                @field(resolver: "Tests\\\Utils\\\Middleware\\\AddFooProperty@resolve")
        }
        ';

        $result = $this->queryViaHttp('
        {
            fail
            pass
        }
        ');

        $this->assertSame(AddFooProperty::DID_RUN, array_get($result, 'data.pass'));
        $this->assertSame(Authenticate::MESSAGE, array_get($result, 'errors.0.message'));
        $this->assertSame('fail', array_get($result, 'errors.0.path.0'));
        $this->assertNull(array_get($result, 'data.fail'));
    }
}
