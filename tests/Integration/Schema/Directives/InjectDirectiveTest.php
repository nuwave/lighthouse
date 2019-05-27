<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class InjectDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateFromInputObjectWithDeepInjection(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);

        $this->schema = '
        type Task {
            id: ID!
            name: String!
            user: User @belongsTo
        }
        
        type User {
            id: ID
        }
        
        type Mutation {
            createTask(input: CreateTaskInput! @spread): Task @create @inject(context: "user.id", name: "user_id")
        }
        
        input CreateTaskInput {
            name: String
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            createTask(input: {
                name: "foo"
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }
}
