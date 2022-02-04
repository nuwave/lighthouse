<?php

namespace Tests\Unit\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\TestCase;

class RenameDirectiveTest extends TestCase
{
    public function testRenameField(): void
    {
        $this->mockResolver([
            'baz' => 'asdf',
        ]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Foo @mock
        }

        type Foo {
            bar: String! @rename(attribute: "baz")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo {
                bar
            }
        }
        ')->assertJson([
            'data' => [
                'foo' => [
                    'bar' => 'asdf',
                ],
            ],
        ]);
    }

    public function testThrowsAnExceptionIfNoAttributeDefined(): void
    {
        $this->expectException(DefinitionException::class);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: String! @rename
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }

    public function testRenameArgument(): void
    {
        $this->mockResolver()
            ->with(
                null,
                ['bar' => 'something']
            );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                baz: String @rename(attribute: "bar")
            ): Boolean @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(baz: "something")
        }
        ');
    }

    public function testRenameListOfInputs(): void
    {
        $this->mockResolver()
            ->with(
                null,
                [
                    'input' => [
                        ['bar' => 'something'],
                    ],
                ]
            );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                input: [FooInput]
            ): Boolean @mock
        }

        input FooInput {
            baz: String @rename(attribute: "bar")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(
                input: [
                    {
                        baz: "something"
                    }
                ]
            )
        }
        ');
    }
}
