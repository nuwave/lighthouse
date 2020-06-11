<?php

namespace Tests\Integration;

use GraphQL\Error\Error;
use Tests\TestCase;
use Tests\Utils\Queries\Bar;
use Tests\Utils\Queries\Foo;

class GraphQLTest extends TestCase
{
    protected $schema = /** @lang GraphQL */ '
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
                .http_build_query(
                    [
                        'query' => /** @lang GraphQL */ '
                        {
                            foo
                        }
                        ',
                    ]
                )
            )
            ->assertExactJson([
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]);
    }

    public function testResolvesNamedOperation(): void
    {
        $this->postGraphQL([
            'query' => /** @lang GraphQL */ '
                query Foo {
                    foo
                }
                query Bar {
                    bar
                }
            ',
            'operationName' => 'Bar',
        ])->assertExactJson([
            'data' => [
                'bar' => Bar::RESULT,
            ],
        ]);
    }

    public function testRejectsEmptyRequest(): void
    {
        $this->postGraphQL([])
             ->assertStatus(200)
             ->assertJson([
                 [
                     'errors' => [
                         [
                             'message' => 'Syntax Error: Unexpected <EOF>',
                             'extensions' => [
                                 'category' => 'graphql',
                             ],
                         ],
                     ],
                 ],
             ]);
    }

    public function testRejectsEmptyQuery(): void
    {
        $this->graphQL(/** @lang GraphQL */ '')
             ->assertStatus(200)
             ->assertJson([
                 'errors' => [
                     [
                         'message' => 'Syntax Error: Unexpected <EOF>',
                         'extensions' => [
                             'category' => 'graphql',
                         ],
                     ],
                 ],
             ]);
    }

    public function testRejectsInvalidQuery(): void
    {
        $result = $this
            ->graphQL(/** @lang GraphQL */ '
            {
                nonExistingField
            }
            ');

        $this->assertStringContainsString(
            'nonExistingField',
            $result->json('errors.0.message')
        );
    }

    public function testHandlesErrorInResolver(): void
    {
        $message = 'foo';
        $this->mockResolver()
            ->willThrowException(new Error($message));

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: ID @mock
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo
            }
            ')
            ->assertJson([
                'data' => [
                    'foo' => null,
                ],
                'errors' => [
                    [
                        'message' => $message,
                    ],
                ],
            ])
            ->assertStatus(200);
    }

    public function testIgnoresInvalidJSONVariables(): void
    {
        $result = $this->postGraphQL([
            'query' => /** @lang GraphQL */ '{}',
            'variables' => /** @lang JSON */ '{}',
        ]);

        $result->assertStatus(200);
    }

    public function testCanResolveBatchedQueries(): void
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
