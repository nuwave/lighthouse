<?php


namespace Tests\Integration\Schema\Directives\Args;


use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class ArgsDirectiveTest extends DBTestCase
{
    use RefreshDatabase;
    private $users;


    /**
     * @test
     */
    public function can_attach_multiple_args_directives_to_one_arg()
    {
        $this->users = factory(User::class, 5)->create();

        $schema = '
        type User {
            id: ID!
            name: String
            email: String
        }
        type Query {
            users(id: ID @eq @validate(rules: ["required", "int"])): [User!]! @paginate(model: "Tests\\\Utils\\\Models\\\User")
        }';

        $query = '{
            users(count: 5 id: '.$this->users->first()->getKey().') {
                data {
                    id
                }
            }
        }';

        $result = $this->execute($schema, $query, true);
        $this->assertCount(1, array_get($result->data, 'users.data'));
    }

    /**
     * @test
     */
    public function no_result_if_both_arg_directives_are_ran()
    {
        $this->users = factory(User::class, 5)->create();

        $schema = '
        type User {
            id: ID!
            name: String
            email: String
        }
        type Query {
            users(id: ID @eq @validate(rules: ["digits:2"])): [User!]! @paginate(model: "Tests\\\Utils\\\Models\\\User")
        }';

        $query = '{
            users(count: 5 id: '.$this->users->first()->getKey().') {
                data {
                    id
                }
            }
        }';

        $result = $this->execute($schema, $query, true);
        $this->assertNull($result->data['users']); // No data because validator fails.
        $this->assertCount(1, $result->errors); // 1 error because validator requires 2 digits.
    }
}