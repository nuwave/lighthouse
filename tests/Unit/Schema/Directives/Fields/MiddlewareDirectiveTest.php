<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\MiddlewareRegistry;

class MiddlewareDirectiveTest extends TestCase
{
    /** @var MiddlewareRegistry */
    protected $middlewareRegistry;

    public function setUp()
    {
        parent::setUp();

        $this->middlewareRegistry = resolve(MiddlewareRegistry::class);
    }

    /**
     * @test
     */
    public function itCanRegisterMiddleware()
    {
        $this->buildSchema('
            type Query {
                foo: String! @middleware(checks: ["auth:web", "auth:admin"])
            }
            type Mutation {
                foo(bar: String!): String! @middleware(checks: ["auth:api"])
            }
        ');
        $query = '
        query FooQuery {
            foo
        }
        ';

        $middleware = $this->middlewareRegistry->forRequest($query);
        $this->assertCount(2, $middleware);
        $this->assertContains('auth:web', $middleware);
        $this->assertContains('auth:admin', $middleware);

        $mutation = '
        mutation CreateFoo {
            foo(bar:"baz")
        }
        ';
        $middleware = $this->middlewareRegistry->forRequest($mutation);
        $this->assertCount(1, $middleware);
        $this->assertContains('auth:api', $middleware);
    }

    /**
     * @test
     */
    public function itCanRegisterMiddlewareWithFragments()
    {
        $this->buildSchema('
        type Query {
            foo: String! @middleware(checks: ["auth:web", "auth:admin"])
        }
        
        type Mutation {
            foo(bar: String!): String! @middleware(checks: ["auth:api"])
        }
        ');

        $query = '
        query FooQuery {
            ...Foo_Fragment
        }
        
        fragment Foo_Fragment on Query {
            foo
        }
        ';
        $middleware = $this->middlewareRegistry->forRequest($query);
        $this->assertCount(2, $middleware);
        $this->assertContains('auth:web', $middleware);
        $this->assertContains('auth:admin', $middleware);

        $mutation = '
        mutation CreateFoo {
            foo(bar:"baz")
        }
        ';
        $middleware = $this->middlewareRegistry->forRequest($mutation);
        $this->assertCount(1, $middleware);
        $this->assertContains('auth:api', $middleware);
    }
}
