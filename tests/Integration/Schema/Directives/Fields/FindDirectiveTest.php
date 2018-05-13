<?php


namespace Tests\Integration\Schema\Directives\Fields;


use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class FindDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

    /** @test */
    public function can_return_single_user()
    {
        $schema = '
        type Company {
            name: String!
        }
        type User {
            id: ID!
            name: String!
        }
        type Query {
            user(id: ID @eq): User @find(model: "User")
        }
        ';

        $userA = factory(User::class)->create(['name' => 'A']);
        $userB = factory(User::class)->create(['name' => 'B']);
        $userC = factory(User::class)->create(['name' => 'C']);


        $result = $this->execute($schema, "{ user(id:{$userB->id}) { name } }");
        $this->assertEquals('B', $result->data['user']['name']);
    }

    /** @test */
    public function can_fail_if_no_model_supplied()
    {
        $schema = '
        type Company {
            name: String!
        }
        type User {
            id: ID!
            name: String!
        }
        type Query {
            user(id: ID @eq): User @find
        }
        ';

        $userA = factory(User::class)->create(['name' => 'A']);
        $userB = factory(User::class)->create(['name' => 'B']);
        $userC = factory(User::class)->create(['name' => 'C']);


        $this->expectException(DirectiveException::class);
        $result = $this->execute($schema, "{ user(id:{$userA->id}) { name } }");
    }

    /** @test */
    public function cannot_fetch_if_multiple_models_match()
    {
        $schema = '
        type Company {
            name: String!
        }
        type User {
            id: ID!
            name: String!
        }
        type Query {
            user(name: String @eq): User @find(model: "User")
        }
        ';

        $userA = factory(User::class)->create(['name' => 'A']);
        $userB = factory(User::class)->create(['name' => 'A']);
        $userC = factory(User::class)->create(['name' => 'B']);


        $result = $this->execute($schema, "{ user(name: \"A\") { name } }");
        $this->assertCount(1, $result->errors);
    }
}