<?php

namespace Tests\Unit\Schema\Directives\Types;

use Tests\TestCase;

class GroupDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanSetNamespaceThroughType()
    {
        $this->assertCanSetNamespace('
        type Query @group(namespace: "Tests\\\Utils\\\Resolvers") {
            me: String @field(resolver: "Foo@bar")
        }
        ');
    }

    /**
     * @test
     */
    public function itCanSetNamespaceThroughTypeExtension()
    {
        $this->assertCanSetNamespace('
        type Query {}
        extend type Query @group(namespace: "Tests\\\Utils\\\Resolvers") {
            me: String @field(resolver: "Foo@bar")
        }
        ');
    }

    protected function assertCanSetNamespace($schema)
    {
        $result = $this->execute($schema, '{ me }');
        $this->assertEquals('foo.bar', $result->data['me']);
    }

    /**
     * @test
     */
    public function itCanSetMiddlewareThroughType()
    {
        $this->assertSetMiddleware('
        type Query @group(middleware: ["foo", "bar"]) {
            me: String @field(resolver: "Tests\\\Utils\\\Resolvers\\\Foo@bar")
        }
        ');
    }

    /**
     * @test
     */
    public function itCanSetMiddlewareThroughTypeExtension()
    {
        $this->assertSetMiddleware('
        type Query {}
        extend type Query @group(middleware: ["foo", "bar"]) {
            me: String @field(resolver: "Tests\\\Utils\\\Resolvers\\\Foo@bar")
        }
        ');
    }

    protected function assertSetMiddleware($schema)
    {
        $this->execute($schema, '{ me }');
        $middleware = graphql()->middleware()->query('me');
        $this->assertCount(2, $middleware);
        $this->assertEquals('foo', $middleware[0]);
        $this->assertEquals('bar', $middleware[1]);
    }
}
