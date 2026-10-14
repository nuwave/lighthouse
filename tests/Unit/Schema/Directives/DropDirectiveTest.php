<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;

final class DropDirectiveTest extends TestCase
{
    public function testDropArgument(): void
    {
        $this->mockResolver()
            ->with(
                null,
                [
                    'bar' => 'something',
                ],
            );

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                baz: String @drop
                bar: String
            ): Boolean @mock
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo(baz: "something", bar: "something")
        }
        GRAPHQL);
    }

    public function testDropListOfInputs(): void
    {
        $this->mockResolver()
            ->with(
                null,
                [
                    'input' => [
                        ['bar' => 'something'],
                    ],
                ],
            );

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                input: [FooInput]
            ): Boolean @mock
        }

        input FooInput {
            baz: String @drop
            bar: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo(
                input: [
                    {
                        baz: "something"
                        bar: "something"
                    }
                ]
            )
        }
        GRAPHQL);
    }
}
