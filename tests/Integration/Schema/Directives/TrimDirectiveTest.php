<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\TestCase;

final class TrimDirectiveTest extends TestCase
{
    public function testTrimsStringArgument(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(bar: String! @trim): String! @mock
        }
        GRAPHQL;

        $this->mockResolver(static fn ($_, array $args): string => $args['bar']);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo(bar: "    foo     ")
        }
        GRAPHQL)->assertJson([
            'data' => [
                'foo' => 'foo',
            ],
        ]);
    }

    public function testTrimsInputArgument(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        input FooInput {
            bar: String!
        }

        type Query {
            foo(input: FooInput! @trim @spread): String! @mock
        }
        GRAPHQL;

        $this->mockResolver(static fn ($_, array $args): string => $args['bar']);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo(input: {
                bar: "    foo     "
            })
        }
        GRAPHQL)->assertJson([
            'data' => [
                'foo' => 'foo',
            ],
        ]);
    }

    public function testTrimsAllFieldInputs(): void
    {
        $this->mockResolver(static fn ($_, array $args): array => $args);

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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
