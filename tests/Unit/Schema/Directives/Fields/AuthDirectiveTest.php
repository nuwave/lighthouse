<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tests\TestCase;

class AuthDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanResolveAuthenticatedUser()
    {
        $user = new class() extends Authenticatable {
            public $foo = 'bar';
        };

        $this->be($user);
        $schema = '
            type User {
                foo: String!
            }
            type Query {
                user: User! @auth
            }
        ';

        $query = $this->buildSchemaFromString($schema)->getQueryType();
        $resolver = array_get($query->config['fields'](), 'user.resolve');
        $this->assertEquals('bar', data_get($resolver(), 'foo'));
    }
}
