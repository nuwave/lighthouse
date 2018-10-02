<?php

namespace Tests\Integration\Schema\Directives\Args;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class ValidateDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanThrowValidationExceptions()
    {
        $users = factory(User::class)->create();

        $schema = '
        type User {
            id: ID!
            name: String
            email: String
        }
        
        type Query {
            users(id: ID @eq @validate(rules: ["digits:2", "min:1000"],)): [User!]! @paginate(model: "Tests\\\Utils\\\Models\\\User")
        }
        ';
        $query = '
        {
            users(count: 5 id: '.$users->first()->getKey().') {
                data {
                    id
                }
            }
        }
        ';
        $result = $this->executeWithoutDebug($schema, $query);

        $this->assertCount(2, array_get($result, 'errors.0.extensions.validation.id'));
    }
}
