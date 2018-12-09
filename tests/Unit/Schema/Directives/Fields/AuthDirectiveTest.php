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

        $schema = '
        type User {
            foo: String!
        }
        
        type Query {
            user: User! @auth
        }
        ';
        $query = '
        {
            user {
                foo
            }
        }           
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame('bar', \Illuminate\Support\Arr::get($result, 'data.user.foo'));
    }
}
