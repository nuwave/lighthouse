<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class FirstDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

    /** @test */
    public function can_return_single_user()
    {
        $schema = '
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

        $query = "
            {
                user(id: {$userB->id}) {
                    name
                }
            }
        ";
        $result = $this->execute($schema, $query);
        $this->assertEquals('B', $result->data['user']['name']);
    }

    /** @test */
    public function can_return_single_user_when_multiple_match()
    {
        $schema = '
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

        $query = '
            {
                user(name: "A") {
                    id
                }
            }
        ';
        $result = $this->execute($schema, $query);
        $this->assertEquals($userA->id, $result->data['user']['id']);
    }
}
