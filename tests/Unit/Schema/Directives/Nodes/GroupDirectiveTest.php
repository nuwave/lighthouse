<?php

namespace Tests\Unit\Schema\Directives\Nodes;

use Tests\TestCase;
use Tests\Utils\Middleware\Authenticate;
use Tests\Utils\Middleware\AddFooProperty;

class GroupDirectiveTest extends TestCase
{
    /**
     * @test
     * @group fixing
     */
    public function itCanSetNamespaces()
    {
        $schema = '
        type Query {
            dummy: Int        
        }
        
        extend type Query @group(namespace: "Tests\\\Utils\\\Resolvers") {
            me: String @field(resolver: "Foo@bar")
        }
        
        extend type Query @group(namespace: "Tests\\\Utils\\\Resolvers") {
            you: String @field(resolver: "Foo@bar")
        }
        ';

        $query = '
        {
            me
        }
        ';
        $result = $this->executeQuery($schema, $query);
        $this->assertEquals('foo.bar', $result->data['me']);

        $query = '
        {
            you
        }
        ';
        $result = $this->executeQuery($schema, $query);
        $this->assertEquals('foo.bar', $result->data['you']);
    }

    /**
     * @test
     */
    public function itCanSetMiddleware()
    {
        $this->schema = '
        type Query @group(middleware: ["Tests\\\Utils\\\Middleware\\\AddFooProperty"]) {
            me: String @field(resolver: "Tests\\\Utils\\\Middleware\\\AddFooProperty@resolve")
        }
        ';
        $query = '
        {
            me
        }
        ';
        $result = $this->queryViaHttp($query);

        $this->assertSame(AddFooProperty::DID_RUN, array_get($result, 'data.me'));
    }

    /**
     * @test
     */
    public function itCanOverrideGroupMiddlewareInField()
    {
        $this->schema = '
        type Query @group(middleware: ["Tests\\\Utils\\\Middleware\\\Authenticate"]) {
            withFoo: String
                @middleware(checks: ["Tests\\\Utils\\\Middleware\\\AddFooProperty"])
                @field(resolver: "Tests\\\Utils\\\Middleware\\\AddFooProperty@resolve")
            withNothing: String
                @middleware(checks: [])
                @field(resolver: "Tests\\\Utils\\\Middleware\\\AddFooProperty@resolve")
            fail: Int
        }
        ';
        $query = '
        {
            withFoo
            withNothing
            fail
        }
        ';
        $result = $this->queryViaHttp($query);

        $this->assertSame(AddFooProperty::DID_RUN, array_get($result, 'data.withFoo'));
        # TODO make sure the request is cleared between middlewares
        $this->assertSame(AddFooProperty::DID_NOT_RUN, array_get($result, 'data.withNothing'));
        $this->assertSame(Authenticate::MESSAGE, array_get($result, 'errors.0.message'));
        $this->assertSame('fail', array_get($result, 'errors.0.path.0'));
        $this->assertNull(array_get($result, 'data.fail'));
    }
}
