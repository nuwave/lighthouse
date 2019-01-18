<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;

class CanDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itThrowsWhenNotAuthenticated()
    {
        $schema = '
        type Query {
            user: User!
                @can(if: "adminOnly")
                @field(resolver: "'.addslashes(self::class).'@resolveUser")
        }
        
        type User {
            name: String
        }
        ';
        $query = '
        {
            user {
                name
            }
        }
        ';

        $this->expectException(AuthenticationException::class);
        $this->execute($schema, $query);
    }

    /**
     * @test
     */
    public function itThrowsIfNotAuthorized()
    {
        $this->be(new User);

        $schema = '
        type Query {
            user: User!
                @can(if: "adminOnly")
                @field(resolver: "'.addslashes(self::class).'@resolveUser")
        }
        
        type User {
            name: String
        }
        ';
        $query = '
        {
            user {
                name
            }
        }
        ';

        $this->expectException(AuthorizationException::class);
        $this->execute($schema, $query);
    }

    /**
     * @test
     */
    public function itPassesAuthIfAuthorized()
    {
        $user = new User;
        $user->name = 'admin';
        $this->be($user);

        $schema = '
        type Query {
            user: User!
                @can(if: "adminOnly")
                @field(resolver: "'.addslashes(self::class).'@resolveUser")
        }
        
        type User {
            name: String
        }
        ';
        $query = '
        {
            user {
                name
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame('foo', array_get($result, 'data.user.name'));
    }

    /**
     * @test
     */
    public function itPassesMultiplePolicies()
    {
        $user = new User;
        $user->name = 'admin';
        $this->be($user);

        $schema = '
        type Query {
            user: User!
                @can(if: ["adminOnly", "alwaysTrue"])
                @field(resolver: "'.addslashes(self::class).'@resolveUser")
        }
        
        type User {
            name: String
        }
        ';
        $query = '
        {
            user {
                name
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame('foo', array_get($result, 'data.user.name'));
    }

    public function resolveUser()
    {
        $user = new User;
        $user->name = 'foo';

        return $user;
    }
}
