<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class CanDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itThrowsIfNotAuthorized(): void
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

    /**
     * @test
     */
    public function itPassesAuthIfAuthorized(): void
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

    /**
     * @test
     */
    public function itAcceptsGuestUser(): void
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

    /**
     * @test
     */
    public function itPassesMultiplePolicies(): void
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

    /**
     * @test
     */
    public function itProcessesTheArgsArgument(): void
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

    public function resolveUser(): User
    {
        $user = new User;
        $user->name = 'foo';

        return $user;
    }
}
