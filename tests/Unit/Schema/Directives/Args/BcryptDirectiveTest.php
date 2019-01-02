<?php

namespace Tests\Unit\Schema\Directives\Args;

use Tests\TestCase;

class BcryptDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanBcryptAnArgument(): void
    {
        $this->schema = '
        type Mutation {
            foo(bar: String @bcrypt): Foo
                @field(resolver: "'.$this->getResolver().'")
        }
        
        type Query {
            foo(bar: String @bcrypt): Foo
                @field(resolver: "'.$this->getResolver().'")
        }
        
        type Foo {
            bar: String
        }
        ';
        $mutationQuery = '
        mutation {
            foo(bar: "password"){
                bar
            }
        }
        ';
        $passwordFromMutation = $this->query($mutationQuery)->json('data.foo.bar');

        $this->assertNotSame('password', $passwordFromMutation);
        $this->assertTrue(password_verify('password', $passwordFromMutation));

        $query = '
        {
            foo(bar: "123"){
                bar
            }
        }
        ';
        $passwordFromQuery = $this->query($query)->json('data.foo.bar');

        $this->assertNotSame('123', $passwordFromQuery);
        $this->assertTrue(password_verify('123', $passwordFromQuery));
    }

    /**
     * @test
     */
    public function itCanBcryptAnArgumentInInputObjectAndArray(): void
    {
        $this->schema = '
        type Query {
            user(input: UserInput): User
                @field(resolver: "'.$this->getResolver('resolveUser').'")
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

        $query = '
        query {
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
        ';

        $result = $this->query($query);

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

    /**
     * @param  $root
     * @param  mixed[] $args
     * @return mixed[]
     */
    public function resolve($root, array $args): array
    {
        return $args;
    }

    /**
     * @param  $root
     * @param  mixed[] $args
     * @return mixed[]
     */
    public function resolveUser($root, array $args): array
    {
        return $args['input'];
    }

    /**
     * @param  string $method
     * @return string
     */
    protected function getResolver(string $method = 'resolve'): string
    {
        return addslashes(self::class)."@{$method}";
    }
}
