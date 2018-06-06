<?php


namespace Tests\Integration\Directives\Fields;


use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\DBTestCase;
use Tests\Utils\CustomGuard;
use Tests\Utils\Models\User;

class AuthDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

    public function testCanResolveAuthDirective()
    {
        /** @var User $user */
        $user = factory(User::class)->create([
            'name' => 'Oliver',
            'email' => 'olivernybroe@gmail.com'
        ]);

        $schema = '
        type User {
            id: ID!
            name: String!
            email: String!
        }
        type Query {
            me: User @auth
        }
        ';

        $query = '
        {
            me {
                name,
                email
            }
        }
        ';

        $this->be($user);
        graphql()->build($schema);
        $data = graphql()->execute($query);
        $expected = [
            'data' => [
                'me' => [
                    'email' => 'olivernybroe@gmail.com',
                    'name' => 'Oliver',
                ],
            ],
        ];

        $this->assertEquals($expected, $data);
    }

    public function testCanResolveWithCustomGuard()
    {
        $this->app['config']["auth.guards.customGuard"] = [
            'driver' => 'customGuard'
        ];

        Auth::extend('customGuard', function () {
            return new CustomGuard();
        });

        $schema = '
        type User {
            id: ID!
            name: String!
            email: String!
        }
        type Query {
            me: User @auth(guard: "customGuard")
        }
        ';

        $query = '
        {
            me {
                name
            }
        }
        ';

        graphql()->build($schema);
        $data = graphql()->execute($query);
        $expected = [
            'data' => [
                'me' => [
                    'name' => 'custom user',
                ],
            ],
        ];

        $this->assertEquals($expected, $data);
    }
}
