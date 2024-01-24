<?php declare(strict_types=1);

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

        $this->mockResolver(static fn ($_, array $args): ?string => $args['bar']);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(bar: "")
        }
        ')->assertExactJson([
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

        $this->mockResolver(static fn ($_, array $args): ?array => $args['bar']);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(bar: [[["", null, "baz"]]])
        }
        ')->assertExactJson([
            'data' => [
                'foo' => [[[null, null, 'baz']]],
            ],
        ]);
    }

    public function testOnField(): void
    {
        $this->mockResolver(static fn ($_, array $args): array => $args);

        $this->schema .= /** @lang GraphQL */ '
        type Foo {
            foo: String
            bar: [String]!
            baz: [[[String]]]!
            qux: Int!
        }

        type Query {
            foo(
                foo: String
                bar: [String]!
                baz: [[[String]]]!
                qux: Int!
            ): Foo! @convertEmptyStringsToNull @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(
                foo: ""
                bar: [""]
                baz: [[[""]]]
                qux: 3
            ) {
                foo
                bar
                baz
                qux
            }
        }
        ')->assertExactJson([
            'data' => [
                'foo' => [
                    'foo' => null,
                    'bar' => [null],
                    'baz' => [[[null]]],
                    'qux' => 3,
                ],
            ],
        ]);
    }

    public function testDoesNotConvertNonNullableArgumentsWhenUsedOnField(): void
    {
        $this->mockResolver(static fn ($_, array $args): array => $args);

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
        ')->assertExactJson([
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

        $this->mockResolver(static fn ($_, array $args): ?string => $args['bar']);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(bar: "")
        }
        ')->assertExactJson([
            'data' => [
                'foo' => null,
            ],
        ]);
    }
}
