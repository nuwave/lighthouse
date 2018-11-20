<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class CanDirectiveTest extends TestCase
{
    /**
     * @test
     * @dataProvider provideAcceptableArgumentNames
     *
     * @param string $argumentName
     */
    public function itThrowsIfNotAuthorized(string $argumentName)
    {
        $this->be(new User());

        $schema = sprintf('
        type Query {
            user: User!
                @can(%s: "adminOnly")
                @field(resolver: "%s@resolveUser")
        }
        
        type User {
            name: String
        }
        ', $argumentName, addslashes(self::class));

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
     * @dataProvider provideAcceptableArgumentNames
     *
     * @param string $argumentName
     */
    public function itPassesAuthIfAuthorized(string $argumentName)
    {
        $user = new User();
        $user->name = 'admin';
        $this->be($user);

        $schema = sprintf('
        type Query {
            user: User!
                @can(%s: "adminOnly")
                @field(resolver: "%s@resolveUser")
        }
        
        type User {
            name: String
        }
        ', $argumentName, addslashes(self::class));

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
     * @dataProvider provideAcceptableArgumentNames
     *
     * @param string $argumentName
     */
    public function itPassesMultiplePolicies(string $argumentName)
    {
        $user = new User();
        $user->name = 'admin';
        $this->be($user);

        $schema = sprintf('
        type Query {
            user: User!
                @can(%s: ["adminOnly", "alwaysTrue"])
                @field(resolver: "%s@resolveUser")
        }
        
        type User {
            name: String
        }
        ', $argumentName, addslashes(self::class));

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

    public function resolveUser(): User
    {
        $user = new User();
        $user->name = 'foo';

        return $user;
    }

    public function provideAcceptableArgumentNames(): array
    {
        return [
            ['if'],
            ['ability'],
        ];
    }
}
