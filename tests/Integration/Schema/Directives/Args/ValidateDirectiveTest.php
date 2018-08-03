<?php


namespace Tests\Integration\Schema\Directives\Args;


use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class ValidateDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itCanAddMessagesToValidation()
    {
        $users = factory(User::class, 10)->create();

        $this->users = factory(User::class, 5)->create();

        $schema = '
        type User {
            id: ID!
            name: String
            email: String
        }
        type Query {
            users(id: ID @eq @validate(rules: ["digits:2"], messages: {digits : "custom message"})): [User!]! @paginate(model: "Tests\\\Utils\\\Models\\\User")
        }';

        $query = '{
            users(count: 5 id: '.$users->first()->getKey().') {
                data {
                    id
                }
            }
        }';

        $result = $this->execute($schema, $query);
        $this->assertEquals("custom message", $result['errors'][0]['message']);
    }


    /**
     * @test
     */
    public function itCanThrowValidationExceptions()
    {
        $users = factory(User::class, 10)->create();

        $schema = '
        type User {
            id: ID!
            name: String
            email: String
        }
        type Query {
            users(id: ID @eq @validate(rules: ["digits:2", "min:1000"],)): [User!]! @paginate(model: "Tests\\\Utils\\\Models\\\User")
        }';

        $query = '{
            users(count: 5 id: '.$users->first()->getKey().') {
                data {
                    id
                }
            }
        }';

        $result = $this->execute($schema, $query);
        $this->assertCount(2, $result['errors']);
    }
}
