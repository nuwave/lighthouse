<?php

namespace Tests\Unit\Auth;

use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Tests\TestCase;
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
            ->assertGraphQLErrorCategory(AuthenticationException::CATEGORY);
    }
}
