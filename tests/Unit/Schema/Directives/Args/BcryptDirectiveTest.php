<?php

namespace Tests\Unit\Schema\Directives\Args;

use Illuminate\Support\Arr;
use Tests\TestCase;

class BcryptDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanBcryptAnArgument()
    {
        $schema = '
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

        $resultFromMutation = $this->execute($schema, $mutationQuery);

        $passwordFromMutation = Arr::get($resultFromMutation, 'data.foo.bar');
        $this->assertNotSame('password', $passwordFromMutation);
        $this->assertTrue(password_verify('password', $passwordFromMutation));

        $query = '
        {
            foo(bar: "123"){
                bar
            }
        }
        ';

        $resultFromQuery = $this->execute($schema, $query);

        $passwordFromQuery = Arr::get($resultFromQuery, 'data.foo.bar');
        $this->assertNotSame('123', $passwordFromQuery);
        $this->assertTrue(password_verify('123', $passwordFromQuery));
    }

    public function resolve($root, $args): array
    {
        return $args;
    }

    protected function getResolver(): string
    {
        return addslashes(self::class).'@resolve';
    }
}
