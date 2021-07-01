<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;

class CleanInputDirectiveTest extends DBTestCase
{
    public function testTrimsStringArgument(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Company {
            id: ID!
            name: String!
        }

        type Mutation {
            createCompany(name: String @cleanInput): Company @create
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createCompany(name: "    foo     ") {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'createCompany' => [
                    'id' => '1',
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testTrimsInputArgument(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        input CompanyInput {
            name: String!
        }

        type Company {
            id: ID!
            name: String!
        }

        type Mutation {
            createCompany(input: CompanyInput @cleanInput @spread): Company @create
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createCompany(input: {
                name: "    foo     "
            }) {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'createCompany' => [
                    'id' => '1',
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testTrimsAllFieldInputs(): void
    {
        $this->mockResolver(static function ($root, array $args): array {
            return $args;
        });

        $this->schema .= /** @lang GraphQL */ '
        type Foo {
            foo: String!
            bar: [String!]!
            baz: Int!
        }

        input FooInput {
            foo: String!
            bar: [String!]!
            baz: Int!
        }

        type Mutation {
            foo(input: FooInput @spread): Foo @cleanInput @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            foo(input: {
                foo: " foo "
                bar: [" bar "]
                baz: 3
            }) {
                foo
                bar
                baz
            }
        }
        ')->assertJson([
            'data' => [
                'foo' => [
                    'foo' => 'foo',
                    'bar' => ['bar'],
                    'baz' => 3,
                ],
            ],
        ]);
    }
}
