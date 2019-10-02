<?php

namespace Tests\Unit\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class SpreadDirectiveTest extends DBTestCase
{
    public function testSpreadsTheInputIntoTheQuery(): void
    {
        factory(User::class, 2)->create();

        $this->schema = '
        type Query {
            user(input: UserInput @spread): User @first
        }

        type User {
            id: ID
        }

        input UserInput {
            id: ID @eq
        }
        ';

        $this->graphQL('
        {
            user(input: {
                id: 2
            }) {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'id' => 2,
                ],
            ],
        ]);
    }

    public function testIgnoresSpreadedInputIfNotGiven(): void
    {
        factory(User::class)->create();

        $this->schema = '
        type Query {
            user(input: UserInput @spread): User @first
        }

        type User {
            id: ID
        }

        input UserInput {
            id: ID @eq
        }
        ';

        $this->graphQL('
        {
            user {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'id' => 1,
                ],
            ],
        ]);
    }

    public function testNestedSpread(): void
    {
        $this->mockResolver()
            ->with(null, [
                'foo' => 1,
                'bar' => 2,
            ]);

        $this->schema = '
        type Query {
            foo(input: Foo @spread): Int @mock
        }
        
        input Foo {
            foo: Int
            bar: Bar @spread
        }
        
        input Bar {
            baz: Int
        }
        ';

        $this->graphQL('
        {
            foo(input: {
                foo: 1
                bar: {
                    baz: 2
                }
            })
        }
        ');
    }
}
