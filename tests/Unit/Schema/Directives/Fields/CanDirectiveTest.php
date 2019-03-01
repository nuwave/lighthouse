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
     * @param  string  $argumentName
     */
    public function itThrowsIfNotAuthorized(string $argumentName): void
    {
        $this->be(new User);

        $this->schema = '
        type Query {
            user: User!
                @can('.$argumentName.': "adminOnly")
                @field(resolver: "'.addslashes(self::class).'@resolveUser")
        }
        
        type User {
            name: String
        }
        ';

        $this->query('
        {
            user {
                name
            }
        }
        ')->assertErrorCategory(AuthorizationException::CATEGORY);
    }

    /**
     * @test
     * @dataProvider provideAcceptableArgumentNames
     *
     * @param  string  $argumentName
     */
    public function itPassesAuthIfAuthorized(string $argumentName): void
    {
        $user = new User;
        $user->name = 'admin';
        $this->be($user);

        $this->schema = '
        type Query {
            user: User!
                @can('.$argumentName.': "adminOnly")
                @field(resolver: "'.addslashes(self::class).'@resolveUser")
        }
        
        type User {
            name: String
        }
        ';

        $this->query('
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    /**
     * @test
     * @dataProvider provideAcceptableArgumentNames
     *
     * @param  string  $argumentName
     */
    public function itAcceptsGuestUser(string $argumentName): void
    {
        if ((float) $this->app->version() < 5.7) {
            $this->markTestSkipped('Version less than 5.7 do not support guest user.');
        }

        $this->schema = '
        type Query {
            user: User!
                @can('.$argumentName.': "guestOnly")
                @field(resolver: "'.addslashes(self::class).'@resolveUser")
        }
        
        type User {
            name: String
        }
        ';

        $this->query('
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    /**
     * @test
     * @dataProvider provideAcceptableArgumentNames
     *
     * @param  string  $argumentName
     */
    public function itPassesMultiplePolicies(string $argumentName): void
    {
        $user = new User;
        $user->name = 'admin';
        $this->be($user);

        $this->schema = '
        type Query {
            user: User!
                @can('.$argumentName.': ["adminOnly", "alwaysTrue"])
                @field(resolver: "'.addslashes(self::class).'@resolveUser")
        }
        
        type User {
            name: String
        }
        ';

        $this->query('
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    /**
     * @test
     * @dataProvider provideAcceptableArgumentNames
     *
     * @param  string  $argumentName
     */
    public function itProcessesTheArgsArgument(string $argumentName): void
    {
        $this->schema = '
        type Query {
            user: User!
                @can('.$argumentName.': "dependingOnArg", args: [false])
                @field(resolver: "'.addslashes(self::class).'@resolveUser")
        }
        
        type User {
            name: String
        }
        ';

        $this->query('
        {
            user {
                name
            }
        }
        ')->assertErrorCategory(AuthorizationException::CATEGORY);
    }

    public function resolveUser(): User
    {
        $user = new User;
        $user->name = 'foo';

        return $user;
    }

    /**
     * @return array[]
     */
    public function provideAcceptableArgumentNames(): array
    {
        return [
            ['if'],
            ['ability'],
        ];
    }
}
