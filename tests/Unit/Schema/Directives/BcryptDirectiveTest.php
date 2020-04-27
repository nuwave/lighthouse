<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;

/**
 * @deprecated
 */
class BcryptDirectiveTest extends TestCase
{
    public function testCanBcryptAnArgument(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: String @bcrypt): Foo @mock
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
            ->jsonGet('data.foo.bar');

        $this->assertNotSame('password', $password);
        $this->assertTrue(password_verify('password', $password));
    }

    public function testCanBcryptAnArgumentInInputObjectAndArray(): void
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
            password: String @bcrypt
            alt_passwords: [String] @bcrypt
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

        $password = $result->jsonGet('data.user.password');
        $this->assertNotSame('password', $password);
        $this->assertTrue(password_verify('password', $password));

        // apply to array
        $altPasswordOne = $result->jsonGet('data.user.alt_passwords.0');
        $this->assertNotSame('alt_password_1', $altPasswordOne);
        $this->assertTrue(password_verify('alt_password_1', $altPasswordOne));

        $altPasswordTwo = $result->jsonGet('data.user.alt_passwords.1');
        $this->assertNotSame('alt_password_2', $altPasswordTwo);
        $this->assertTrue(password_verify('alt_password_2', $altPasswordTwo));

        // apply to (nested) input
        $friendPasswordOne = $result->jsonGet('data.user.friends.0.password');
        $this->assertNotSame('friend_password_1', $friendPasswordOne);
        $this->assertTrue(password_verify('friend_password_1', $friendPasswordOne));

        $friendPasswordTwo = $result->jsonGet('data.user.friends.1.password');
        $this->assertNotSame('friend_password_2', $friendPasswordTwo);
        $this->assertTrue(password_verify('friend_password_2', $friendPasswordTwo));

        $friendPasswordThree = $result->jsonGet('data.user.friends.2.password');
        $this->assertNotSame('friend_password_3', $friendPasswordThree);
        $this->assertTrue(password_verify('friend_password_3', $friendPasswordThree));

        $friendPasswordFour = $result->jsonGet('data.user.friends.2.friends.0.password');
        $this->assertNotSame('friend_password_4', $friendPasswordFour);
        $this->assertTrue(password_verify('friend_password_4', $friendPasswordFour));
    }
}
