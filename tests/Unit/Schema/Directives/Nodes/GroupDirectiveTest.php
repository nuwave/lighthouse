<?php

namespace Tests\Unit\Schema\Directives\Nodes;

use Tests\TestCase;

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
        $schema = '
        type Query {
            dummy: Int
        }
        
        extend type Query @group(middleware: ["foo", "bar"]) {
            me: String @field(resolver: "Tests\\\Utils\\\Resolvers\\\Foo@bar")
        }
        ';
        $query = '
        {
            me
        }
        ';
        $this->executeQuery($schema, $query);

        $middleware = graphql()->middleware()->query('me');
        $this->assertCount(2, $middleware);
        $this->assertEquals('foo', $middleware[0]);
        $this->assertEquals('bar', $middleware[1]);
    }
}
