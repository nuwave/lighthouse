<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Illuminate\Foundation\Auth\User as Authenticatable;

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

        $schema = $this->buildSchemaFromString('
        type User {
            foo: String!
        }
        
        type Query {
            user: User! @auth
        }
        ');

        $query = $schema->getType('Query');
        $resolver = array_get($query->config['fields'](), 'user.resolve');
        $this->assertEquals('bar', data_get($resolver(null, []), 'foo'));
    }
}
