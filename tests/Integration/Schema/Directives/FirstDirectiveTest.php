<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class FirstDirectiveTest extends DBTestCase
{
    public function testReturnsASingleUser(): void
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

        factory(User::class)->create(['name' => 'A']);
        $userB = factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'C']);

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

    public function testReturnsASingleUserWhenMultiplesMatch(): void
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
        factory(User::class)->create(['name' => 'A']);
        factory(User::class)->create(['name' => 'B']);

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
