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
            ->assertGraphQLErrorMessage('GraphQL Request must include at least one of those two parameters: "query" or "queryId"')
            ->assertGraphQLErrorCategory('request');
    }

    public function testEmptyQuery(): void
    {
        $this->graphQL(/** @lang GraphQL */ '')
            ->assertGraphQLErrorMessage('GraphQL Request parameter "query" is required and must not be empty.')
            ->assertGraphQLErrorCategory('request');
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
        $config = $this->app->make(ConfigRepository::class);
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

    public function testReturnsMultipleErrors(): void
    {
        $this->schema = /** @lang GraphQL */'
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
            ->assertGraphQLErrorMessage('Field TestInput.string of required type String! was not provided.')
            ->assertGraphQLErrorMessage('Field TestInput.integer of required type Int! was not provided.');
    }
}
