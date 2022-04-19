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
            foo(input: FooInput! @spread): Foo! @trim @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(input: {
                foo: ""
                bar: [""]
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
                    'foo' => null,
                    'bar' => [null],
                    'baz' => 3,
                ],
            ],
        ]);
    }
}
