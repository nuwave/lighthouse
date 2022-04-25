<?php

namespace Tests\Integration\Schema\Directives;

use Tests\TestCase;

final class ConvertEmptyStringsToNullDirectiveTest extends TestCase
{
    public function testArgument(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: String @convertEmptyStringsToNull): String @mock
        }
        ';

        $this->mockResolver(function ($_, array $args): ?string {
            return $args['bar'];
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(bar: "")
        }
        ')->assertJson([
            'data' => [
                'foo' => null,
            ],
        ]);
    }

    public function testMatrix(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: [[[String]]] @convertEmptyStringsToNull): [[[String]]] @mock
        }
        ';

        $this->mockResolver(function ($_, array $args): ?array {
            return $args['bar'];
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(bar: [[["", null, "baz"]]])
        }
        ')->assertJson([
            'data' => [
                'foo' => [[[null, null, "baz"]]],
            ],
        ]);
    }

    public function testFieldInputs(): void
    {
        $this->mockResolver(static function ($root, array $args): array {
            return $args;
        });

        $this->schema .= /** @lang GraphQL */ '
        type Foo {
            foo: String
            bar: [String]!
            baz: Int!
        }

        input FooInput {
            foo: String
            bar: [String]!
            baz: Int!
        }

        type Query {
            foo(
                foo: String
                bar: [String]!
                baz: Int!
            ): Foo! @convertEmptyStringsToNull @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(
                foo: ""
                bar: [""]
                baz: 3
            ) {
                foo
                bar
                baz
            }
        }
        ')->assertJson([
            'data' => [
                'foo' => [
                    'foo' => null,
                    'bar' => [null],
                    'baz' => 3,
                ],
            ],
        ]);
    }

    public function testDoesNotConvertNonNullableArguments(): void
    {
        $this->mockResolver(static function ($root, array $args): array {
            return $args;
        });

        $this->schema .= /** @lang GraphQL */ '
        type Foo {
            foo: String!
            bar: [String!]!
            baz: [[[String!]]]!
        }

        type Query {
            foo(
                foo: String!
                bar: [String!]
                baz: [[[String!]]]
            ): Foo! @convertEmptyStringsToNull @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(
                foo: ""
                bar: [""]
                baz: [[[""]]]
            ) {
                foo
                bar
                baz
            }
        }
        ')->assertJson([
            'data' => [
                'foo' => [
                    'foo' => '',
                    'bar' => [''],
                    'baz' => [[['']]],
                ],
            ],
        ]);
    }
}
