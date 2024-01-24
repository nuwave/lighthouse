<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\TestCase;

final class SpreadDirectiveTest extends TestCase
{
    public function testSpread(): void
    {
        $this->mockResolver()
            ->with(null, [
                'foo' => 1,
            ]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: Foo @spread): Int @mock
        }

        input Foo {
            foo: Int
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(input: {
                foo: 1
            })
        }
        ');
    }

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

    public function testSpreadOnListThrows(): void
    {
        $this->expectExceptionObject(new DefinitionException('Cannot use @spread on argument Query.foo:input with a list type.'));
        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            foo(input: [Foo!]! @spread): Int
        }

        input Foo {
            bar: Int!
        }
        ');
    }
}
