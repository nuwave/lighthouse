<?php declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;
use Tests\Utils\Queries\Bar;
use Tests\Utils\Queries\Foo;

final class GraphQLTest extends TestCase
{
    protected string $schema = /** @lang GraphQL */ <<<'GRAPHQL'
    type Query {
        foo: Int
        bar: String
    }
    GRAPHQL;

    public function testResolvesQueryViaPostRequest(): void
    {
        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo
            }
            GRAPHQL)
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
                        'query' => /** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo
                        }
                        GRAPHQL,
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
                'query' => /** @lang GraphQL */ <<<'GRAPHQL'
                    query Foo {
                        foo
                    }
                    query Bar {
                        bar
                    }
                GRAPHQL,
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
                    'query' => /** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo
                        }
                    GRAPHQL,
                ],
                [
                    'query' => /** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            bar
                        }
                    GRAPHQL,
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
