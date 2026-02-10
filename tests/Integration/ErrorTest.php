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
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        GRAPHQL)
            ->assertStatus(200)
            ->assertGraphQLErrorMessage('GraphQL Request must include at least one of those two parameters: "query" or "queryId"');
    }

    public function testRejectsInvalidQuery(): void
    {
        $result = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                {
                    nonExistingField
                }
        GRAPHQL);
        $result->assertStatus(200);

        $this->assertStringContainsString(
            'nonExistingField',
            (string) $result->json('errors.0.message'),
        );
    }

    /** @dataProvider parseSourceLocations */
    #[DataProvider('parseSourceLocations')]
    public function testReturnsFullGraphQLError(bool $parseSourceLocations): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.parse_source_location', $parseSourceLocations);

        $message = 'some error';
        $this->mockResolver(static fn (): Error => new Error($message));

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo: ID @mock
                }
        GRAPHQL;

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
                'query' => /** @lang GraphQL */ <<<'GRAPHQL'
                {}
                GRAPHQL,
                'variables' => '{}',
            ])
            ->assertStatus(200);
    }

    public function testHandlesErrorInResolver(): void
    {
        $message = 'foo';
        $this->mockResolver(static fn () => throw new Error($message));

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo: ID @mock
                }
        GRAPHQL;

        $this
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
                    ],
                ],
            ]);
    }

    public function testRethrowsInternalExceptions(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $this->mockResolver(static fn () => throw new \Exception('foo'));

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo: ID @mock
                }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo
                        }
            GRAPHQL)
            ->assertStatus(200)
            ->assertJsonCount(1, 'errors');

        $config->set('lighthouse.debug', DebugFlag::RETHROW_INTERNAL_EXCEPTIONS);

        $this->expectException(\Exception::class);
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                {
                    foo
                }
        GRAPHQL);
    }

    public function testReturnsMultipleErrors(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                input TestInput {
                    string: String!
                    integer: Int!
                }
        
                type Query {
                    foo(input: TestInput): ID
                }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo(input: {})
                        }
            GRAPHQL)
            ->assertStatus(200)
            ->assertGraphQLErrorMessage('Field TestInput.string of required type String! was not provided.')
            ->assertGraphQLErrorMessage('Field TestInput.integer of required type Int! was not provided.');
    }

    public function testSplitsErrorsForMultipleOperations(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo: Int
                    bar: String
                }
        GRAPHQL;

        $dispatcher = $this->app->make(EventsDispatcher::class);
        $dispatcher->listen(
            StartExecution::class,
            static fn (StartExecution $startExecution) => throw new \Exception($startExecution->operationName ?? 'unknown operation'),
        );

        $this
            ->postGraphQL([
                [
                    'query' => /** @lang GraphQL */ <<<'GRAPHQL'
                                            query Foo {
                                                foo
                                            }
                    GRAPHQL,
                    'operationName' => 'Foo',
                ],
                [
                    'query' => /** @lang GraphQL */ <<<'GRAPHQL'
                                            query Bar {
                                                bar
                                            }
                    GRAPHQL,
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

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    success: Int! @mock(key: "success")
                    fail: Int @mock(key: "fail")
                }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                    {
                        success
                        fail
                    }
        GRAPHQL)
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

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    success: Int! @mock(key: "success")
                    fail: Int! @mock(key: "fail")
                }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                    {
                        success
                        fail
                    }
        GRAPHQL)
            ->assertStatus(200)
            ->assertJsonMissingPath('data')
            ->assertGraphQLError($error);
    }

    public function testUnknownTypeInVariableDefinition(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo(bar: ID): ID
                }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        query ($bar: UnknownType) {
                            foo(bar: $bar)
                        }
            GRAPHQL)
            ->assertGraphQLErrorMessage('Unknown type "UnknownType".');
    }

    public function testAssertGraphQLDebugMessage(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $message = 'foo';

        $this->mockResolver(static fn () => throw new \Exception($message));

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo: ID @mock
                }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo
                        }
            GRAPHQL)
            ->assertStatus(200)
            /** @see FormattedError::$internalErrorMessage */
            ->assertGraphQLErrorMessage('Internal server error')
            ->assertGraphQLDebugMessage($message);
    }

    public function testAssertGraphQLErrorClientSafe(): void
    {
        $error = new Error('foo');

        $this->mockResolver(static fn () => throw $error);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo: ID @mock
                }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo
                        }
            GRAPHQL)
            ->assertStatus(200)
            ->assertGraphQLError($error);
    }

    public function testAssertGraphQLErrorNonClientSafe(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $exception = new \Exception('foo');

        $this->mockResolver(static fn () => throw $exception);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo: ID @mock
                }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo
                        }
            GRAPHQL)
            ->assertStatus(200)
            /** @see FormattedError::$internalErrorMessage */
            ->assertGraphQLErrorMessage('Internal server error')
            ->assertGraphQLError($exception);
    }
}
