<?php

namespace Tests\Integration;

use Exception;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
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

    public function testRethrowsInternalExceptions(): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = app(ConfigRepository::class);
        $config->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $this->mockResolver()
            ->willThrowException(new Exception('foo'));

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
            ->assertJsonCount(1, 'errors');

        $config->set('lighthouse.debug', DebugFlag::RETHROW_INTERNAL_EXCEPTIONS);

        $this->expectException(Exception::class);
        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }
}
