<?php declare(strict_types=1);

namespace Tests\Unit\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Tests\TestCase;
use Tests\Utils\Models\Team;
use Tests\Utils\Queries\Foo;

final class GuardDirectiveTest extends TestCase
{
    public function testGuardDefault(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Int @guard
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertGraphQLErrorMessage(AuthenticationException::MESSAGE);
    }

    public function testGuardWith(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Int @guard(with: ["web"])
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertJson([
            'errors' => [
                [
                    'message' => AuthenticationException::MESSAGE,
                    'extensions' => [
                        'guards' => [
                            'web',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testPassesOneFieldButThrowsInAnother(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Int
            bar: String @guard
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
            bar
        }
        GRAPHQL)->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
                'bar' => null,
            ],
            'errors' => [
                [
                    'message' => AuthenticationException::MESSAGE,
                    'path' => [
                        'bar',
                    ],
                ],
            ],
        ]);
    }

    public function testGuardHappensBeforeOtherDirectivesIfAddedFromType(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query @guard {
            user: User!
                @can(ability: "adminOnly")
                @mock
        }

        type User {
            name: String
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                user {
                    name
                }
            }
            GRAPHQL)
            ->assertGraphQLErrorMessage(AuthenticationException::MESSAGE);
    }

    public function testGuardAppliesToFieldsOnExtendTypeOnly(): void
    {
        $value = 42;
        $this->mockResolver($value);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            unguarded: Int! @mock
        }

        extend type Query @guard {
            guarded: Int! @mock
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                guarded
            }
        GRAPHQL)
            ->assertGraphQLErrorMessage(AuthenticationException::MESSAGE);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                unguarded
            }
        GRAPHQL)
            ->assertExactJson([
                'data' => [
                    'unguarded' => $value,
                ],
            ]);
    }

    public function testMultiGuardWithAuthorization(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('auth.providers', array_merge($config->get('auth.providers'), [
            'teams' => [
                'driver' => 'eloquent',
                'model' => Team::class,
            ],
        ]));
        $config->set('auth.guards', array_merge($config->get('auth.guards'), [
            'team' => [
                'driver' => 'session',
                'provider' => 'teams',
            ],
        ]));

        $team = new Team();
        $team->id = 1;
        $team->name = 'Test';

        $auth = $this->app->make(AuthFactory::class);
        $auth->guard('team')->setUser($team);

        $this->mockResolver($team);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Team {
            id: ID!
        }

        type Query {
            team: Team!
                @guard(with: ["team"])
                @can(ability: "onlyTeams", model: "Tests\\Utils\\Models\\Team")
                @mock
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            team {
                id
            }
        }
        GRAPHQL)
            ->assertGraphQLErrorFree()
            ->assertJson([
                'data' => [
                    'team' => [
                        'id' => (string) $team->id,
                    ],
                ],
            ]);
    }
}
