<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;

final class SpreadDirectiveTest extends TestCase
{
    public function testNestedSpread(): void
    {
        $this->mockResolver()
            ->with(null, [
                'foo' => 1,
                'baz' => 2,
            ]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: Foo @spread): Int @mock
        }

        input Foo {
            foo: Int
            bar: Bar @spread
        }

        input Bar {
            baz: Int
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(input: {
                foo: 1
                bar: {
                    baz: 2
                }
            })
        }
        ');
    }

    public function testNestedSpreadInList(): void
    {
        $this->mockResolver()
            ->with(null, [
                'input' => [
                    [
                        'baz' => 1,
                    ],
                ],
            ]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: [Foo!]!): Int @mock
        }

        input Foo {
            bar: Bar! @spread
        }

        input Bar {
            baz: Int!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(input: [
                {
                    bar: {
                        baz: 1
                    }
                }
            ])
        }
        ');
    }
}
