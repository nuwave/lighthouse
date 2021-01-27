<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Company;

class TrimDirectiveTest extends DBTestCase
{
    public function testTrimsStringArgument(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Company {
            id: ID!
            name: String!
        }

        type Mutation {
            createCompany(name: String @trim): Company @create
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
            createCompany(input: CompanyInput @trim @spread): Company @create
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

    public function testTrimsOnNonRootField(): void
    {
        factory(Company::class, 3)->create();

        $this->schema .= /** @lang GraphQL */ '
        type Company {
            id(foo: String @trim): ID!
        }

        type Query {
            companies: [Company!]! @all
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            companies {
                id(foo: " bar ")
            }
        }
        ')->assertJson([
            'data' => [
                'companies' => [
                    'id' => '1',
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
            foo(input: FooInput @spread): Foo @trim @mock
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
