<?php

namespace Tests\Unit\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class SpreadDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itResolvesWhenSpreadedInputDoesNotExistOnQuery(): void
    {
        factory(User::class)->create();

        $this->schema = '
        type Query{
            user(input: UserInput @spread): User @first
        }
        type User{
            id: ID
        }
        input UserInput{
            id: ID @eq
        }
        ';

        $this->query('{
            user{
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

    /**
     * @test
     */
    public function itResolvesWhenSpreadedInputDoesExistOnQuery(): void
    {
        factory(User::class)->create();

        $this->schema = '
        type Query{
            user(input: UserInput @spread): User @first
        }
        type User{
            id: ID
        }
        input UserInput{
            id: ID @eq
        }
        ';

        $this->query('{
            user(input: {id: 1}){
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
}
