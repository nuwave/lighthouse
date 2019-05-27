<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class FirstDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itReturnsASingleUser(): void
    {
        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            user(id: ID @eq): User @first(model: "User")
        }
        ';

        $userA = factory(User::class)->create(['name' => 'A']);
        $userB = factory(User::class)->create(['name' => 'B']);
        $userC = factory(User::class)->create(['name' => 'C']);

        $this->graphQL("
        {
            user(id: {$userB->id}){
                name
            }
        }
        ")->assertJson([
            'data' => [
                'user' => [
                    'name' => 'B',
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itReturnsASingleUserWhenMultiplesMatch(): void
    {
        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            user(name: String @eq): User @first(model: "User")
        }
        ';

        $userA = factory(User::class)->create(['name' => 'A']);
        $userB = factory(User::class)->create(['name' => 'A']);
        $userC = factory(User::class)->create(['name' => 'B']);

        $this->graphQL('
        {
            user(name: "A") {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'id' => $userA->id,
                ],
            ],
        ]);
    }
}
