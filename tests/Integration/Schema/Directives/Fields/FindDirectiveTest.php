<?php


namespace Tests\Integration\Schema\Directives\Fields;


use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\User;

class FindDirectiveTest extends DBTestCase
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
        $this->expectException(DirectiveException::class);
        $schema = $this->buildSchemaFromString('
        type User {
            id: ID!
            name: String!
        }
        type Query {
            user(id: ID @eq): User @find
        }
        ');
        $schema->getQueryType()->getField('user')->resolveFn();
    }

    /** @test */
    public function cannot_fetch_if_multiple_models_match()
    {
        $schema = '
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

    /** @test */
    public function can_use_scopes()
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
            user(name: String @eq, company: String!): User @find(model: "User" scopes: [companyName])
        }
        ';

        $companyA = factory(Company::class)->create(['name' => 'CompanyA']);
        $companyB = factory(Company::class)->create(['name' => 'CompanyB']);
        $userA = factory(User::class)->create(['name' => 'A', 'company_id' => $companyA->id]);
        $userB = factory(User::class)->create(['name' => 'A', 'company_id' => $companyB->id]);
        $userC = factory(User::class)->create(['name' => 'B', 'company_id' => $companyA->id]);


        $result = $this->execute($schema, "{ user(name: \"A\" company: \"CompanyA\") { id, name } }");
        $this->assertEquals($userA->id, $result->data['user']['id']);
        $this->assertEquals('A', $result->data['user']['name']);
    }
}
