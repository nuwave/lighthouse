<?php

namespace Tests\Unit\Schema\Directives\Nodes;

use Tests\TestCase;
use Tests\Utils\Middleware\Authenticate;

class GroupDirectiveTest extends TestCase
{
    /**
     * @test
     * @group fixing
     */
    public function itCanSetNamespaces()
    {
        $schema = '
        extend type Query @group(namespace: "Tests\\\Utils\\\Resolvers") {
            me: String @field(resolver: "Foo@bar")
        }
        
        extend type Query @group(namespace: "Tests\\\Utils\\\Resolvers") {
            you: String @field(resolver: "Foo@bar")
        }
        ' . $this->placeholderQuery();

        $query = '
        {
            me
        }
        ';
        $result = $this->executeQuery($schema, $query);
        $this->assertSame('foo.bar', $result->data['me']);

        $query = '
        {
            you
        }
        ';
        $result = $this->executeQuery($schema, $query);
        $this->assertSame('foo.bar', $result->data['you']);
    }

    /**
     * @test
     */
    public function itCanSetMiddleware()
    {
        $this->schema = '
        type Query @group(middleware: ["Tests\\\Utils\\\Middleware\\\CountRuns"]) {
            me: Int @field(resolver: "Tests\\\Utils\\\Middleware\\\CountRuns@resolve")
        }
        ';
        $query = '
        {
            me
        }
        ';
        $result = $this->queryViaHttp($query);

        $this->assertSame(1, \Illuminate\Support\Arr::get($result, 'data.me'));
    }

    /**
     * @test
     */
    public function itCanOverrideGroupMiddlewareInField()
    {
        $this->schema = '
        type Query @group(middleware: ["Tests\\\Utils\\\Middleware\\\Authenticate"]) {
            withFoo: Int
                @middleware(checks: ["Tests\\\Utils\\\Middleware\\\CountRuns"])
                @field(resolver: "Tests\\\Utils\\\Middleware\\\CountRuns@resolve")
            withNothing: Int
                @middleware(checks: [])
                @field(resolver: "Tests\\\Utils\\\Middleware\\\CountRuns@resolve")
            foo: Int
        }
        ';
        $query = '
        {
            withFoo
            withNothing
            foo
        }
        ';
        $result = $this->queryViaHttp($query);

        $this->assertSame(1, \Illuminate\Support\Arr::get($result, 'data.withFoo'));
        $this->assertSame(1, \Illuminate\Support\Arr::get($result, 'data.withNothing'));
        $this->assertSame(Authenticate::MESSAGE, \Illuminate\Support\Arr::get($result, 'errors.0.message'));
        $this->assertSame('foo', \Illuminate\Support\Arr::get($result, 'errors.0.path.0'));
        $this->assertNull(\Illuminate\Support\Arr::get($result, 'data.foo'));
    }
}
