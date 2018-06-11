<?php


namespace Tests\Unit\Directives\Fields;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Mockery\Mock;
use Nuwave\Lighthouse\Schema\Directives\Fields\AuthDirective;
use Nuwave\Lighthouse\Schema\ResolveInfo;
use Tests\TestCase;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

class AuthDirectiveTest extends TestCase
{
    public function testCanResolveAuthDirective()
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

        $authFactory = Mockery::mock(AuthFactory::class);
        $authFactory->expects('guard')->andReturn(
            new class($user) {
                protected $user;

                public function __construct($user)
                {
                    $this->user = $user;
                }

                public function user()
                {
                    return $this->user;
                }
            });

        $this->graphql->directives()->add(new AuthDirective($authFactory));
        $schema = $this->graphql->build($schema);

        $me = $schema->type('Query')->field('me');
        $resolver = $me->resolver(new ResolveInfo($me));


        $this->assertEquals('bar', $resolver()->result()->foo);
    }

    public function testCanResolveWithCustomGuard()
    {
        $authFactory = Mockery::mock(AuthFactory::class);
        $authFactory->expects('guard')->with('customGuard')->andReturn(
            new class {
                public function user()
                {
                    return new class() extends Authenticatable {
                        public $someData = "from custom guard";
                    };
                }
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

        $this->graphql->directives()->add(new AuthDirective($authFactory));
        $schema = $this->graphql->build($schema);

        $me = $schema->type('Query')->field('me');
        $resolver = $me->resolver(new ResolveInfo($me));

        $this->assertEquals("from custom guard", $resolver()->result()->someData);
    }
}
