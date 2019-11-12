<?php

namespace Tests\Unit\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Tests\TestCase;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

class CanDirectiveTest extends TestCase
{
    public function testThrowsIfNotAuthorized(): void
    {
        $this->be(new User);

        $this->schema = '
        type Query {
            user: User!
                @can(ability: "adminOnly")
                @field(resolver: "'.$this->qualifyTestResolver('resolveUser').'")
        }
        
        type User {
            name: String
        }
        ';

        $this->graphQL('
        {
            user {
                name
            }
        }
        ')->assertErrorCategory(AuthorizationException::CATEGORY);
    }

    public function testPassesAuthIfAuthorized(): void
    {
        $user = new User;
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->schema = '
        type Query {
            user: User!
                @can(ability: "adminOnly")
                @field(resolver: "'.$this->qualifyTestResolver('resolveUser').'")
        }
        
        type User {
            name: String
        }
        ';

        $this->graphQL('
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testAcceptsGuestUser(): void
    {
        if ((float) $this->app->version() < 5.7) {
            $this->markTestSkipped('Version less than 5.7 do not support guest user.');
        }

        $this->schema = '
        type Query {
            user: User!
                @can(ability: "guestOnly")
                @field(resolver: "'.$this->qualifyTestResolver('resolveUser').'")
        }
        
        type User {
            name: String
        }
        ';

        $this->graphQL('
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testPassesMultiplePolicies(): void
    {
        $user = new User;
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->schema = '
        type Query {
            user: User!
                @can(ability: ["adminOnly", "alwaysTrue"])
                @field(resolver: "'.$this->qualifyTestResolver('resolveUser').'")
        }
        
        type User {
            name: String
        }
        ';

        $this->graphQL('
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testProcessesTheArgsArgument(): void
    {
        $this->schema = '
        type Query {
            user: User!
                @can(ability: "dependingOnArg", args: [false])
                @field(resolver: "'.$this->qualifyTestResolver('resolveUser').'")
        }

        type User {
            name: String
        }
        ';

        $this->graphQL('
        {
            user {
                name
            }
        }
        ')->assertErrorCategory(AuthorizationException::CATEGORY);
    }

    public function testSendInputArgumentToPolicy(): void
    {
        $this->be(new User);
        $this->schema = '
        type Query {
            users(key1: String, key2: String, key3: String): [User]!
                @can(ability:"severalArgs", input: true)
                @field(resolver: "'.$this->qualifyTestResolver('resolveUser').'")
        }
        
        type User {
            name: String
        }
        ';

        $variables = [
            'key1' => 'foo',
            'key2' => 'foo2',
            'key3' => 'foo3',
        ];

        $this->postGraphQL(
            [
                'operationName' => 'Users',
                'query' => 'query Users($key1: String, $key2: String, $key3: String) {
                                users(key1: $key1, key2: $key2, key3: $key3) {
                                    name
                                }
                            }
                            ',
                'variables' => $variables,
            ]
        )->assertOk();
    }

    public function resolveUser(): User
    {
        $user = new User;
        $user->name = 'foo';

        return $user;
    }
}
