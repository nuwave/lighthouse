<?php


namespace Tests\Unit\Directives\Fields;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Schema\ResolveInfo;
use Tests\TestCase;

class AuthDirectiveTest extends TestCase
{
    /** @test */
    public function can_resolve_auth_directive()
    {
        $user = new class() extends Authenticatable {
            public $foo = 'bar';
        };

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


        $this->be($user);
        $schema = graphql()->build($schema);

        $me = $schema->type('Query')->field('me');
        $resolver = $me->resolver(new ResolveInfo($me));


        $this->assertEquals('bar', $resolver()->result()->foo);
    }

    /** @test */
    public function can_resolve_with_custom_guard()
    {
        $this->app['config']["auth.guards.customGuard"] = [
            'driver' => 'customGuard'
        ];

        Auth::extend('customGuard', function () {
            return new class {
                public function user()
                {
                    return new class() extends Authenticatable {
                        public $someData = "from custom guard";
                    };
                }
            };
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

        $schema = graphql()->build($schema);

        $me = $schema->type('Query')->field('me');
        $resolver = $me->resolver(new ResolveInfo($me));

        $this->assertEquals("from custom guard", $resolver()->result()->someData);
    }
}