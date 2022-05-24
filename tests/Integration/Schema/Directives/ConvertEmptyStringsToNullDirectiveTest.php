<?php

namespace Tests\Integration\Schema\Directives;

use Tests\TestCase;

final class ConvertEmptyStringsToNullDirectiveTest extends TestCase
{
    public function testOnArgument(): void
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

    public function testOnArgumentWithMatrix(): void
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
                'foo' => [[[null, null, 'baz']]],
            ],
        ]);
    }

    public function testOnField(): void
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

    public function testDoesNotConvertNonNullableArgumentsWhenUsedOnField(): void
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

    public function testConvertsNonNullableArgumentsWhenUsedOnArgument(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: String! @convertEmptyStringsToNull): String @mock
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
}
