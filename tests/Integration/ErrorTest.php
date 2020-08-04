<?php

namespace Tests\Integration;

use GraphQL\Error\Error;
use Tests\TestCase;

class ErrorTest extends TestCase
{
    public function testMissingQuery(): void
    {
        $this->postGraphQL([])
            ->assertJson([
                'errors' => [
                    [
                        'message' => 'GraphQL Request must include at least one of those two parameters: "query" or "queryId"',
                        'extensions' => [
                            'category' => 'request',
                        ],
                    ],
                ],
            ]);
    }

    public function testRejectsInvalidQuery(): void
    {
        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            nonExistingField
        }
        ');

        $this->assertStringContainsString(
            'nonExistingField',
            $result->json('errors.0.message')
        );
    }

    public function testIgnoresInvalidJSONVariables(): void
    {
        $result = $this->postGraphQL([
            'query' => /** @lang GraphQL */ '{}',
            'variables' => '{}',
        ]);

        $result->assertStatus(200);
    }

    public function testRejectsEmptyRequest(): void
    {
        $this->postGraphQL([])
            ->assertStatus(200)
            ->assertJson([
                'errors' => [
                    [
                        'message' => 'GraphQL Request must include at least one of those two parameters: "query" or "queryId"',
                        'extensions' => [
                            'category' => 'request',
                        ],
                    ],
                ],
            ]);
    }

    public function testRejectsEmptyQuery(): void
    {
        $this->graphQL('')
            ->assertStatus(200)
            ->assertJson([
                'errors' => [
                    [
                        'message' => 'GraphQL Request must include at least one of those two parameters: "query" or "queryId"',
                        'extensions' => [
                            'category' => 'request',
                        ],
                    ],
                ],
            ]);
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
}
