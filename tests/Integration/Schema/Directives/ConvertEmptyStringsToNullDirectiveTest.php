<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Schema\Directives\ConvertEmptyStringsToNullDirective;
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

    public function testConvertsEmptyStringToNullWithFieldDirective(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: String): FooResponse
                @convertEmptyStringsToNull
                @field(resolver: "Tests\\\Utils\\\Mutations\\\ReturnReceivedInput")
        }

        type FooResponse {
            bar: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(bar: "") {
                bar
            }
        }
        ')->assertExactJson([
            'data' => [
                'foo' => [
                    'bar' => null,
                ],
            ],
        ]);
    }

    public function testConvertsEmptyStringToNullWithGlobalFieldMiddleware(): void
    {
        config(['lighthouse.field_middleware' => [
            ConvertEmptyStringsToNullDirective::class,
        ]]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: String): FooResponse
                @field(resolver: "Tests\\\Utils\\\Mutations\\\ReturnReceivedInput")
        }

        type FooResponse {
            bar: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(bar: "") {
                bar
            }
        }
        ')->assertExactJson([
            'data' => [
                'foo' => [
                    'bar' => null,
                ],
            ],
        ]);
    }

    public function testConvertsEmptyStringToNullWithFieldDirectiveAndInputType(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: FooInput): FooInputResponse
                @convertEmptyStringsToNull
                @field(resolver: "Tests\\\Utils\\\Mutations\\\ReturnReceivedInput")
        }

        input FooInput {
            bar: String
        }

        type FooInputResponse {
            input: FooResponse
        }

        type FooResponse {
            bar: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(input: {
                bar: ""
            }) {
                input {
                    bar
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'foo' => [
                    'input' => [
                        'bar' => null,
                    ],
                ],
            ],
        ]);
    }

    public function testConvertsEmptyStringToNullWithGlobalFieldMiddlewareAndInputType(): void
    {
        config(['lighthouse.field_middleware' => [
            ConvertEmptyStringsToNullDirective::class,
        ]]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: FooInput): FooInputResponse
                @field(resolver: "Tests\\\Utils\\\Mutations\\\ReturnReceivedInput")
        }

        input FooInput {
            bar: String
        }

        type FooInputResponse {
            input: FooResponse
        }

        type FooResponse {
            bar: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(input: {
                bar: ""
            }) {
                input {
                    bar
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'foo' => [
                    'input' => [
                        'bar' => null,
                    ],
                ],
            ],
        ]);
    }
}
