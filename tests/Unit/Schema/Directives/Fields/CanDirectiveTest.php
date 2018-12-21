<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Illuminate\Support\Arr;
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

        $schema = '
        type Query {
            user: User!
                @can('.$argumentName.': "adminOnly")
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
     * @dataProvider provideAcceptableArgumentNames
     *
     * @param string $argumentName
     */
    public function itPassesAuthIfAuthorized(string $argumentName)
    {
        $user = new User();
        $user->name = 'admin';
        $this->be($user);

        $schema = '
        type Query {
            user: User!
                @can('.$argumentName.': "adminOnly")
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
     * @dataProvider provideAcceptableArgumentNames
     *
     * @param string $argumentName
     */
    public function itAcceptsGuestUser(string $argumentName)
    {
        if ((float) $this->app->version() < 5.7) {
            $this->markTestSkipped('Version less than 5.7 do not support guest user.');
        }

        $schema = '
        type Query {
            user: User!
                @can('.$argumentName.': "guestOnly")
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

        $this->assertSame('foo', Arr::get($result, 'data.user.name'));
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

        $schema = '
        type Query {
            user: User!
                @can('.$argumentName.': ["adminOnly", "alwaysTrue"])
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

        $this->assertSame('foo', Arr::get($result, 'data.user.name'));
    }

    /**
     * @test
     * @dataProvider provideAcceptableArgumentNames
     *
     * @param string $argumentName
     */
    public function itProcessesTheArgsArgument(string $argumentName)
    {
        $schema = '
        type Query {
            user: User!
                @can('.$argumentName.': "dependingOnArg", args: [false])
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
