<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;

class MiddlewareDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanRegisterMiddleware()
    {
        schema()->register('
            type Query {
                foo: String! @middleware(checks: ["auth:web", "auth:admin"])
                bar: String!
            }
            type Mutation {
                foo(bar: String!): String! @middleware(checks: ["auth:api"])
                bar(baz: String!): String!
            }
        ');

        $query = 'query FooQuery { foo }';
        $middleware = graphql()->middleware()->forRequest($query);
        $this->assertCount(2, $middleware);
        $this->assertContains('auth:web', $middleware);
        $this->assertContains('auth:admin', $middleware);

        $query = 'mutation CreateFoo { foo(bar:"baz") }';
        $middleware = graphql()->middleware()->forRequest($query);
        $this->assertCount(1, $middleware);
        $this->assertContains('auth:api', $middleware);
    }
}
