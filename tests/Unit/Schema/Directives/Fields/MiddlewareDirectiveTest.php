<?php

namespace Tests\Unit\Schema\Directives\Fields;

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
            foo: Int
                @middleware(checks: ["Tests\\\Utils\\\Middleware\\\AddFooProperty"])
                @field(resolver: "'. addslashes(self::class).'@resolveFooMiddleware")
        }
        ';

        $result = $this->queryViaHttp($query);

        $this->assertSame(42, array_get($result, 'data.foo'));
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

    public function resolveFooMiddleware($root, $args, Context $context): int
    {
        $this->assertSame(AddFooProperty::VALUE, $context->request->foo);

        return 42;
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
}
