<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;

class HashDirectiveTest extends TestCase
{
    public function testCanHashAnArgument(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: String @hash): Foo @mock
        }

        type Foo {
            bar: String
        }
        ';

        $this->mockResolver(function ($root, $args) {
            return $args;
        });

        $password = $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(bar: "password") {
                    bar
                }
            }
            ')
            ->json('data.foo.bar');

        $this->assertNotSame('password', $password);
        $this->assertTrue(password_verify('password', $password));
    }

    public function testCanHashAnArgumentInInputObjectAndArray(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(input: UserInput): User @mock
        }

        type User {
            password: String!
            alt_passwords: [String]
            friends: [User]
        }

        input UserInput {
            password: String @hash
            alt_passwords: [String] @hash
            friends: [UserInput]
        }
        ';

        $this->mockResolver(function ($root, array $args) {
            return $args['input'];
        });

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            user(input: {
                password: "password"
                alt_passwords: ["alt_password_1", "alt_password_2"]
                friends: [
                    { password: "friend_password_1" }
                    { password: "friend_password_2" }
                    {
                        password: "friend_password_3"
                        friends: [
                            { password: "friend_password_4" }
                        ]
                    }
                ]
            }) {
                password
                alt_passwords
                friends {
                    password
                    friends {
                        password
                    }
                }
            }
        }
        ');

        $password = $result->json('data.user.password');
        $this->assertNotSame('password', $password);
        $this->assertTrue(password_verify('password', $password));

        // apply to array
        $altPasswordOne = $result->json('data.user.alt_passwords.0');
        $this->assertNotSame('alt_password_1', $altPasswordOne);
        $this->assertTrue(password_verify('alt_password_1', $altPasswordOne));

        $altPasswordTwo = $result->json('data.user.alt_passwords.1');
        $this->assertNotSame('alt_password_2', $altPasswordTwo);
        $this->assertTrue(password_verify('alt_password_2', $altPasswordTwo));

        // apply to (nested) input
        $friendPasswordOne = $result->json('data.user.friends.0.password');
        $this->assertNotSame('friend_password_1', $friendPasswordOne);
        $this->assertTrue(password_verify('friend_password_1', $friendPasswordOne));

        $friendPasswordTwo = $result->json('data.user.friends.1.password');
        $this->assertNotSame('friend_password_2', $friendPasswordTwo);
        $this->assertTrue(password_verify('friend_password_2', $friendPasswordTwo));

        $friendPasswordThree = $result->json('data.user.friends.2.password');
        $this->assertNotSame('friend_password_3', $friendPasswordThree);
        $this->assertTrue(password_verify('friend_password_3', $friendPasswordThree));

        $friendPasswordFour = $result->json('data.user.friends.2.friends.0.password');
        $this->assertNotSame('friend_password_4', $friendPasswordFour);
        $this->assertTrue(password_verify('friend_password_4', $friendPasswordFour));
    }
}
