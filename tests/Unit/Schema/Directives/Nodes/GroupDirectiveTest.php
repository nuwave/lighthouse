<?php

namespace Tests\Unit\Schema\Directives\Nodes;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\Context;
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
        type Query {
            dummy: Int
        }
        
        extend type Query @group(middleware: ["Tests\\\Utils\\\Middleware\\\AddFooProperty"]) {
            me: String @field(resolver: "'. addslashes(self::class).'@resolve")
        }
        ';
        $query = '
        {
            me
        }
        ';
        $result = $this->queryViaHttp($query);
        # TODO this throws because of the escape slashes

        $this->assertSame('Mario', array_get($result, 'data.me'));
    }

    public function resolve($root, $args, Context $context): string
    {
        $this->assertSame(AddFooProperty::VALUE, $context->request->foo);

        return 'Mario';
    }
}
