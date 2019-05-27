<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;
use Tests\Utils\Models\User;

class AuthDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanResolveAuthenticatedUser(): void
    {
        $user = new User(['foo' => 'bar']);
        $this->be($user);

        $this->schema = '
        type User {
            foo: String!
        }
        
        type Query {
            user: User! @auth
        }
        ';

        $this->graphQL('
        {
            user {
                foo
            }
        }           
        ')->assertJson([
            'data' => [
                'user' => [
                    'foo' => 'bar',
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanResolveAuthenticatedUserWithGuardArgument(): void
    {
        $user = new User(['foo' => 'bar']);

        $this->app['auth']->guard('api')->setUser($user);

        $this->schema = '
        type User {
            foo: String!
        }
        
        type Query {
            user: User! @auth(guard: "api")
        }
        ';

        $this->graphQL('
        {
            user {
                foo
            }
        }           
        ')->assertJson([
            'data' => [
                'user' => [
                    'foo' => 'bar',
                ],
            ],
        ]);
    }
}
