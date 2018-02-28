<?php

namespace Nuwave\Lighthouse\Tests\Schema\Directives\Fields;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Nuwave\Lighthouse\Tests\TestCase;

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
        type Query {
            user: User! @auth
        }
        ';

        $field = schema()->register($schema)->first();
        $resolver = array_get($field, 'resolve');
        $this->assertEquals('bar', data_get($resolver(), 'foo'));
    }
}
