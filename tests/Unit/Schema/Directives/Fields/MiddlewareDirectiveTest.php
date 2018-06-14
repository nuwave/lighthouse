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
        $this->buildSchemaFromString('
            type Query {
                foo: String! @middleware(checks: ["auth:web", "auth:admin"])
                bar: String!
            }
            type Mutation {
                foo(bar: String!): String! @middleware(checks: ["auth:api"])
                bar(baz: String!): String!
            }
        ');

        $middleware = graphql()->middleware()->forRequest('
            query FooQuery {
                foo
            }
        ');

        $this->assertCount(2, $middleware);
        $this->assertContains('auth:web', $middleware);
        $this->assertContains('auth:admin', $middleware);

        $middleware = graphql()->middleware()->forRequest('
            mutation CreateFoo {
                foo(bar:"baz")
            }
        ');

        $this->assertCount(1, $middleware);
        $this->assertContains('auth:api', $middleware);
    }

    /**
     * @test
     */
    public function itCanRegisterMiddlewareWithFragments()
    {
        $this->buildSchemaFromString('
            type Query {
                foo: String! @middleware(checks: ["auth:web", "auth:admin"])
                bar: String!
            }
            type Mutation {
                foo(bar: String!): String! @middleware(checks: ["auth:api"])
                bar(baz: String!): String!
            }
        ');

        $middleware = graphql()->middleware()->forRequest('
            query FooQuery {
                ...Foo_Fragment
            }
            
            fragment Foo_Fragment on Query {
                foo
            }
        ');
        $this->assertCount(2, $middleware);
        $this->assertContains('auth:web', $middleware);
        $this->assertContains('auth:admin', $middleware);

        $middleware = graphql()->middleware()->forRequest('
            mutation CreateFoo {
                foo(bar:"baz")
            }
        ');

        $this->assertCount(1, $middleware);
        $this->assertContains('auth:api', $middleware);
    }
}
