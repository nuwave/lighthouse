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
    public function itCanSetNamespaces(): void
    {
        $this->schema = '
        extend type Query @group(namespace: "Tests\\\Utils\\\Resolvers") {
            me: String @field(resolver: "Foo@bar")
        }
        
        extend type Query @group(namespace: "Tests\\\Utils\\\Resolvers") {
            you: String @field(resolver: "Foo@bar")
        }
        '.$this->placeholderQuery();

        $this->query('
        {
            me
        }
        ')->assertJson([
            'data' => [
                'me' => 'foo.bar'
            ]
        ]);

        $this->query('
        {
            you
        }
        ')->assertJson([
            'data' => [
                'you' => 'foo.bar'
            ]
        ]);
    }

    /**
     * @test
     */
    public function itCanSetMiddleware(): void
    {
        $this->schema = '
        type Query @group(middleware: ["Tests\\\Utils\\\Middleware\\\CountRuns"]) {
            me: Int @field(resolver: "Tests\\\Utils\\\Middleware\\\CountRuns@resolve")
        }
        ';

        $this->query('
        {
            me
        }
        ')->assertJson([
            'data' => [
                'me' => 1
            ]
        ]);
    }

    /**
     * @test
     */
    public function itCanOverrideGroupMiddlewareInField(): void
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

        $this->query('
        {
            withFoo
            withNothing
            foo
        }
        ')->assertJson([
            'data' => [
                'withFoo' => 1,
                'withNothing' => 1,
                'foo' => null,
            ],
            'errors' => [
                [
                    'message' => Authenticate::MESSAGE,
                    'path' => [
                        'foo'
                    ]
                ]
            ]
        ]);
    }
}
