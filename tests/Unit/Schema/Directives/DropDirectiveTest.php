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

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                baz: String @drop
                bar: String
            ): Boolean @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(baz: "something", bar: "something")
        }
        ');
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

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                input: [FooInput]
            ): Boolean @mock
        }

        input FooInput {
            baz: String @drop
            bar: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
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
        ');
    }
}
