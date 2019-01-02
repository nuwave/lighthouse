<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Tests\Utils\Models\User;

class AuthDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanResolveAuthenticatedUser()
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

        $this->query('
        {
            user {
                foo
            }
        }           
        ')->assertJson([
            'data' => [
                'user' => [
                    'foo' => 'bar'
                ]
            ]
        ]);
    }
}
