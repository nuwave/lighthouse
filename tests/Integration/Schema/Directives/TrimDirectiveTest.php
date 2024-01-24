<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\TestCase;

final class TrimDirectiveTest extends TestCase
{
    public function testTrimsStringArgument(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: String! @trim): String! @mock
        }
        ';

        $this->mockResolver(static fn ($_, array $args): string => $args['bar']);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(bar: "    foo     ")
        }
        ')->assertJson([
            'data' => [
                'foo' => 'foo',
            ],
        ]);
    }

    public function testTrimsInputArgument(): void
    {
        $this->schema = /** @lang GraphQL */ '
        input FooInput {
            bar: String!
        }

        type Query {
            foo(input: FooInput! @trim @spread): String! @mock
        }
        ';

        $this->mockResolver(static fn ($_, array $args): string => $args['bar']);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(input: {
                bar: "    foo     "
            })
        }
        ')->assertJson([
            'data' => [
                'foo' => 'foo',
            ],
        ]);
    }

    public function testTrimsAllFieldInputs(): void
    {
        $this->mockResolver(static fn ($_, array $args): array => $args);

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

        type Query {
            foo(input: FooInput! @spread): Foo! @trim @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
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
