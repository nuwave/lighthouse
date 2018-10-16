<?php

namespace Tests\Unit\Schema\Directives\Nodes;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\MiddlewareRegistry;

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

        $middleware = resolve(MiddlewareRegistry::class)->query('me');
        $this->assertCount(2, $middleware);
        $this->assertEquals('foo', $middleware[0]);
        $this->assertEquals('bar', $middleware[1]);
    }

    /**
     * @test
     */
    public function itHandlesDefaultNamespacesForMutations()
    {
        $schema = '
        type Query {
            dummy: Int
        }

        type Mutation {
            dummy: Int
        }
        
        extend type Mutation {
            foo: String @field(resolver: "FooMutation@bar")
        }
        ';

        $mutation = '        
        mutation{
            foo
        }        
        ';
        
        $result = $this->executeQuery($schema, $mutation);
        $this->assertEquals('bar', $result->data['foo']);
    }

    /**
     * @test
     */
    public function itHandlesDefaultNamespacesForQueries()
    {
        $schema = '
        type Query {
            dummy: Int
        }

        type Mutation {
            dummy: Int
        }
        
        extend type Query {
            foo: String @field(resolver: "FooQuery@bar")
        }
        ';

        $query = '                
        query{
            foo
        }        
        ';
        
        $result = $this->executeQuery($schema, $query);

        $this->assertEquals('bar', $result->data['foo']);
    }
}
