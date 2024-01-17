<?php

namespace Tests\Integration;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Tests\TestCase;

final class ErrorTest extends TestCase
{
    public function testMissingQuery(): void
    {
        $this->postGraphQL([])
            ->assertStatus(200)
            ->assertGraphQLErrorMessage('GraphQL Request must include at least one of those two parameters: "query" or "queryId"')
            ->assertGraphQLErrorCategory('request');
    }

    public function testEmptyQuery(): void
    {
        $this->graphQL(/** @lang GraphQL */ '')
            ->assertStatus(200)
            ->assertGraphQLErrorMessage('GraphQL Request must include at least one of those two parameters: "query" or "queryId"')
            ->assertGraphQLErrorCategory('request');
    }

    public function testRejectsInvalidQuery(): void
    {
        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            nonExistingField
        }
        ');
        $result->assertStatus(200);

        $this->assertStringContainsString(
            'nonExistingField',
            $result->json('errors.0.message')
        );
    }

    public function testReturnsFullGraphQLError(): void
    {
        $message = 'some error';
        $this->mockResolver(static function () use ($message): Error {
            return new Error($message);
        });

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: ID @mock
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */'
            {
                foo
            }
            ')
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'foo' => null,
                ],
                'errors' => [
                    [
                        'message' => $message,
                        'path' => ['foo'],
                        'locations' => [
                            [
                                'line' => 3,
                                'column' => 17,
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function testReturnsGraphQLErrorWithoutLocationNode(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.parse_source_location', false);

        $message = 'some error';
        $this->mockResolver(static function () use ($message): Error {
            return new Error($message);
        });

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
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'foo' => null,
                ],
                'errors' => [
                    [
                        'message' => $message,
                        'path' => ['foo'],
                    ],
                ],
            ]);
    }

    public function testIgnoresInvalidJSONVariables(): void
    {
        $this
            ->postGraphQL([
                'query' => /** @lang GraphQL */ '{}',
                'variables' => '{}',
            ])
            ->assertStatus(200);
    }

    public function testRejectsEmptyRequest(): void
    {
        $this->postGraphQL([])
            ->assertStatus(200)
            ->assertGraphQLErrorMessage('GraphQL Request must include at least one of those two parameters: "query" or "queryId"')
            ->assertGraphQLErrorCategory('request');
    }

    public function testRejectsEmptyQuery(): void
    {
        $this->graphQL('')
            ->assertStatus(200)
            ->assertGraphQLErrorMessage('GraphQL Request must include at least one of those two parameters: "query" or "queryId"')
            ->assertGraphQLErrorCategory('request');
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
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'foo' => null,
                ],
                'errors' => [
                    [
                        'message' => $message,
                    ],
                ],
            ]);
    }

    public function testRethrowsInternalExceptions(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        assert($config instanceof ConfigRepository);
        $config->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $this->mockResolver()
            ->willThrowException(new \Exception('foo'));

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
            ->assertStatus(200)
            ->assertJsonCount(1, 'errors');

        $config->set('lighthouse.debug', DebugFlag::RETHROW_INTERNAL_EXCEPTIONS);

        $this->expectException(\Exception::class);
        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }

    public function testReturnsMultipleErrors(): void
    {
        $this->schema = /** @lang GraphQL */ '
        input TestInput {
            string: String!
            integer: Int!
        }

        type Query {
            foo(input: TestInput): ID
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(input: {})
            }
            ')
            ->assertStatus(200)
            ->assertGraphQLErrorMessage('Field TestInput.string of required type String! was not provided.')
            ->assertGraphQLErrorMessage('Field TestInput.integer of required type Int! was not provided.');
    }

    public function testAssertGraphQLDebugMessage(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        assert($config instanceof ConfigRepository);
        $config->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $message = 'foo';

        $this->mockResolver()
            ->willThrowException(new \Exception($message));

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
            ->assertStatus(200)
            /** @see FormattedError::$internalErrorMessage */
            ->assertGraphQLErrorMessage('Internal server error')
            ->assertGraphQLDebugMessage($message);
    }

    public function testAssertGraphQLErrorClientSafe(): void
    {
        $error = new Error('foo');

        $this->mockResolver()
            ->willThrowException($error);

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
            ->assertStatus(200)
            ->assertGraphQLError($error);
    }

    public function testAssertGraphQLErrorNonClientSafe(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        assert($config instanceof ConfigRepository);
        $config->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $exception = new \Exception('foo');

        $this->mockResolver()
            ->willThrowException($exception);

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
            ->assertStatus(200)
            /** @see FormattedError::$internalErrorMessage */
            ->assertGraphQLErrorMessage('Internal server error')
            ->assertGraphQLError($exception);
    }
}

