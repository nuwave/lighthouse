<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeleteDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

    /** @test */
    public function itDeletesUser()
    {
        $user = factory(User::class)->create(['name' => 'A']);

        $schema = '
        type User {
            id: ID!
            name: String
        }
        
        type Mutation {
            deleteUser(id: ID): User @delete
        }
        
        type Query {}
        ';
        $query = "
        mutation {
            deleteUser(id: {$user->id}) {
                name
            }
        }
        ";
        $this->assertCount(1, User::all());

        $result = $this->execute($schema, $query);

        $this->assertEquals('A', $result->data['deleteUser']['name']);

        $this->assertCount(0, User::all());
    }
}
