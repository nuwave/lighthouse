<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;
use Tests\Utils\QueriesSecondary\Foo;

class NamespaceDirectiveTest extends TestCase
{
    public function testCanSetNamespaceOnField(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: String
                @field(resolver: "Foo")
                @namespace(field: "Tests\\\Utils\\\QueriesSecondary")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => Foo::NOT_THE_ANSWER,
            ],
        ]);
    }

    public function testCanSetNamespaceFromType(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query @namespace(field: "Tests\\\Utils\\\QueriesSecondary") {
            foo: String @field(resolver: "Foo")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => Foo::NOT_THE_ANSWER,
            ],
        ]);
    }

    public function testPrefersFieldNamespaceOverTypeNamespace(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query @namespace(field: "Tests\\\Utils\\\QueriesSecondary") {
            foo: Int
                @field(resolver: "Foo")
                @namespace(field: "Tests\\\Utils\\\Queries")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => \Tests\Utils\Queries\Foo::THE_ANSWER,
            ],
        ]);
    }
}
