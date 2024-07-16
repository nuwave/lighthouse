<?php declare(strict_types=1);

namespace Tests\Integration;

use Composer\InstalledVersions;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Laragraph\Utils\BadRequestGraphQLException;
use Nuwave\Lighthouse\Events\StartExecution;
use Tests\TestCase;

final class ErrorTest extends TestCase
{
    public function testRejectsEmptyRequest(): void
    {
        $version = (float) InstalledVersions::getVersion('laragraph/utils');

        if ($version >= 2) {
            $this->expectException(BadRequestGraphQLException::class);
            $this->postGraphQL([]);
        } else {
            $this->postGraphQL([])
                ->assertStatus(200)
                ->assertGraphQLErrorMessage('GraphQL Request must include at least one of those two parameters: "query" or "queryId"');
        }
    }

    public function testRejectsEmptyQuery(): void
    {
        $this->graphQL('')
            ->assertStatus(200)
            ->assertGraphQLErrorMessage('GraphQL Request must include at least one of those two parameters: "query" or "queryId"');
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
            $result->json('errors.0.message'),
        );
    }

    /** @dataProvider parseSourceLocations */
    public function testReturnsFullGraphQLError(bool $parseSourceLocations): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.parse_source_location', $parseSourceLocations);

        $message = 'some error';
        $this->mockResolver(static fn (): Error => new Error($message));

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: ID @mock
        }
        ';

        $response = $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo
            }
            GRAPHQL)
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

        $locationsJsonPath = 'errors.0.locations';
        $parseSourceLocations
            ? $response->json($locationsJsonPath) === [
                [
                    'line' => 2,
                    'column' => 5,
                ],
            ]
            : $response->assertJsonMissingPath($locationsJsonPath);
    }

    /** @return iterable<array{bool}> */
    public static function parseSourceLocations(): iterable
    {
        yield [true];
        yield [false];
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

    public function testHandlesErrorInResolver(): void
    {
        $message = 'foo';
        $this->mockResolver(static fn () => throw new Error($message));

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
        $config->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $this->mockResolver(static fn () => throw new \Exception('foo'));

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

    public function testSplitsErrorsForMultipleOperations(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int
            bar: String
        }
        ';

        $dispatcher = $this->app->make(EventsDispatcher::class);
        $dispatcher->listen(
            StartExecution::class,
            static fn (StartExecution $startExecution) => throw new \Exception($startExecution->operationName ?? 'unknown operation'),
        );

        $this
            ->postGraphQL([
                [
                    'query' => /** @lang GraphQL */ '
                        query Foo {
                            foo
                        }
                        ',
                    'operationName' => 'Foo',
                ],
                [
                    'query' => /** @lang GraphQL */ '
                        query Bar {
                            bar
                        }
                        ',
                    'operationName' => 'Bar',
                ],
            ])
            ->assertExactJson([
                [
                    'errors' => [
                        [
                            /** @see FormattedError::$internalErrorMessage */
                            'message' => 'Internal server error',
                            'extensions' => [
                                'debugMessage' => 'Foo',
                            ],
                        ],
                    ],
                ],
                [
                    'errors' => [
                        [
                            /** @see FormattedError::$internalErrorMessage */
                            'message' => 'Internal server error',
                            'extensions' => [
                                'debugMessage' => 'Bar',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function testReturnsPartialDataIfNullableFieldFails(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $successValue = 1;
        $this->mockResolver($successValue, 'success');

        $error = new \Exception('fail');
        $this->mockResolver(static fn () => throw $error, 'fail');

        $this->schema = /** @lang GraphQL */ '
        type Query {
            success: Int! @mock(key: "success")
            fail: Int @mock(key: "fail")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
            {
                success
                fail
            }
            ')
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'success' => $successValue,
                    'fail' => null,
                ],
            ])
            ->assertGraphQLError($error);
    }

    public function testReturnsNoDataIfNonNullableFieldFails(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $successValue = 1;
        $this->mockResolver($successValue, 'success');

        $error = new \Exception('fail');
        $this->mockResolver(static fn () => throw $error, 'fail');

        $this->schema = /** @lang GraphQL */ '
        type Query {
            success: Int! @mock(key: "success")
            fail: Int! @mock(key: "fail")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
            {
                success
                fail
            }
            ')
            ->assertStatus(200)
            ->assertJsonMissingPath('data')
            ->assertGraphQLError($error);
    }

    public function testUnknownTypeInVariableDefinition(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: ID): ID
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($bar: UnknownType) {
                foo(bar: $bar)
            }
            ')
            ->assertGraphQLErrorMessage('Unknown type "UnknownType".');
    }

    public function testAssertGraphQLDebugMessage(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $message = 'foo';

        $this->mockResolver(static fn () => throw new \Exception($message));

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

        $this->mockResolver(static fn () => throw $error);

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
        $config->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $exception = new \Exception('foo');

        $this->mockResolver(static fn () => throw $exception);

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
