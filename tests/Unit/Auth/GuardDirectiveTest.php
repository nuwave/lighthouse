<?php

namespace Tests\Unit\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Tests\TestCase;
use Tests\Utils\Models\Team;
use Tests\Utils\Queries\Foo;

final class GuardDirectiveTest extends TestCase
{
    public function testGuardDefault(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @guard
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertGraphQLErrorMessage(AuthenticationException::MESSAGE);
    }

    public function testGuardWith(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @guard(with: ["web"])
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
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

    /**
     * @deprecated remove cast in v6
     */
    public function testSpecifyGuardAsString(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @guard(with: "web")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
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
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int
            bar: String @guard
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
            bar
        }
        ')->assertJson([
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
        $this->schema = /** @lang GraphQL */ '
        type Query @guard {
            user: User!
                @can(ability: "adminOnly")
                @mock
        }

        type User {
            name: String
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                user {
                    name
                }
            }
            ')
            ->assertGraphQLErrorMessage(AuthenticationException::MESSAGE);
    }

    public function testMultiGuardWithAuthorization(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        assert($config instanceof ConfigRepository);

        $config->set('auth.providers', array_merge($config->get('auth.providers'), [
            'teams' => [
                'driver' => 'eloquent',
                'model' => Team::class,
            ]
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
        assert($auth instanceof AuthFactory);
        $auth->guard('team')->setUser($team);

        $this->mockResolver($team);

        $this->schema = /** @lang GraphQL */ '
        type Team {
            id: ID!
        }

        type Query {
            team: Team!
                @guard(with: ["team"])
                @can(ability: "onlyTeams", model: "Tests\\\\Utils\\\\Models\\\\Team")
                @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            team {
                id
            }
        }
        ')
            ->assertGraphQLErrorFree()
            ->assertJson([
                'data' => [
                    'team' => [
                        'id' => (string)$team->id,
                    ],
                ],
            ]);
    }
}
