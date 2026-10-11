<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\TestCase;

final class RenameDirectiveTest extends TestCase
{
    public function testRenameField(): void
    {
        $this->mockResolver([
            'baz' => 'asdf',
        ]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Foo @mock
        }

        type Foo {
            bar: String! @rename(attribute: "baz")
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo {
                bar
            }
        }
        GRAPHQL)->assertJson([
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

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: String! @rename
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL);
    }

    public function testRenameArgument(): void
    {
        $this->mockResolver()
            ->with(
                null,
                ['bar' => 'something'],
            );

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                baz: String @rename(attribute: "bar")
            ): Boolean @mock
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo(baz: "something")
        }
        GRAPHQL);
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
                ],
            );

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                input: [FooInput]
            ): Boolean @mock
        }

        input FooInput {
            baz: String @rename(attribute: "bar")
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo(
                input: [
                    {
                        baz: "something"
                    }
                ]
            )
        }
        GRAPHQL);
    }
}
