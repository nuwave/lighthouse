<?php declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;
use Tests\Utils\Queries\Bar;
use Tests\Utils\Queries\Foo;

final class GraphQLTest extends TestCase
{
    protected string $schema = /** @lang GraphQL */ '
    type Query {
        foo: Int
        bar: String
    }
    ';

    public function testResolvesQueryViaPostRequest(): void
    {
        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo
            }
            ')
            ->assertGraphQLErrorFree()
            ->assertExactJson([
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]);
    }

    public function testResolvesQueryViaGetRequest(): void
    {
        $this
            ->getJson(
                'graphql?'
                . http_build_query(
                    [
                        'query' => /** @lang GraphQL */ '
                        {
                            foo
                        }
                        ',
                    ],
                ),
            )
            ->assertExactJson([
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]);
    }

    public function testResolvesNamedOperation(): void
    {
        $this
            ->postGraphQL([
                'query' => /** @lang GraphQL */ '
                    query Foo {
                        foo
                    }
                    query Bar {
                        bar
                    }
                ',
                'operationName' => 'Bar',
            ])
            ->assertExactJson([
                'data' => [
                    'bar' => Bar::RESULT,
                ],
            ]);
    }

    public function testResolveBatchedQueries(): void
    {
        $this
            ->postGraphQL([
                [
                    'query' => /** @lang GraphQL */ '
                        {
                            foo
                        }
                        ',
                ],
                [
                    'query' => /** @lang GraphQL */ '
                        {
                            bar
                        }
                        ',
                ],
            ])
            ->assertExactJson([
                [
                    'data' => [
                        'foo' => Foo::THE_ANSWER,
                    ],
                ],
                [
                    'data' => [
                        'bar' => Bar::RESULT,
                    ],
                ],
            ]);
    }
}
