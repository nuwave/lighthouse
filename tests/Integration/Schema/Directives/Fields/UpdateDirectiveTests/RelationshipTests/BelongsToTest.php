<?php

namespace Tests\Integration\Schema\Directives\Fields\UpdateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class BelongsToTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanUpdateWithBelongsTo()
    {
        factory(User::class, 2)->create();
        factory(Task::class)->create([
            'name' => 'bar',
            'user_id' => 1,
        ]);

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
            updateTask(input: UpdateTaskInput!): Task @update(flatten: true)
        }
        
        input UpdateTaskInput {
            id: ID!
            name: String
            user_id: ID
        }
        '.$this->placeholderQuery();

        $this->query('
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                user_id: 2
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
                'updateTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '2',
                    ],
                ],
            ],
        ]);

        $task = Task::first();
        $this->assertSame('2', $task->user_id);
        $this->assertSame('foo', $task->name);
    }
}
