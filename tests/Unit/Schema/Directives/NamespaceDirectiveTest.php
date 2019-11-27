<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;

class NamespaceDirectiveTest extends TestCase
{
    public function testCanSetNamespaceOnField(): void
    {
        $this->schema = '
        type Query {
            foo: String @field(resolver: "Foo@resolve") @namespace(field: "Tests\\\Utils\\\QueriesSecondary")
        }
        ';

        $this->graphQL('
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => \Tests\Utils\QueriesSecondary\Foo::NOT_THE_ANSWER,
            ],
        ]);
    }

    public function testCanSetNamespaceFromType(): void
    {
        $this->schema = '
        type Query @namespace(field: "Tests\\\Utils\\\QueriesSecondary") {
            foo: String @field(resolver: "Foo@resolve")
        }
        ';

        $this->graphQL('
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => \Tests\Utils\QueriesSecondary\Foo::NOT_THE_ANSWER,
            ],
        ]);
    }

    public function testPrefersFieldNamespaceOverTypeNamespace(): void
    {
        $this->schema = '
        type Query @namespace(field: "Tests\\\Utils\\\QueriesSecondary") {
            foo: Int @field(resolver: "Foo@resolve") @namespace(field: "Tests\\\Utils\\\Queries")
        }
        ';

        $this->graphQL('
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
