<?php

namespace Tests\Integration\Schema\Directives\Fields\UpdateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
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

        $schema = '
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
        $query = '
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                user: {
                    
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame('1', Arr::get($result, 'data.updateTask.id'));
        $this->assertSame('foo', Arr::get($result, 'data.updateTask.name'));
        $this->assertSame('2', Arr::get($result, 'data.updateTask.user.id'));

        $task = Task::first();
        $this->assertSame('2', $task->user_id);
        $this->assertSame('foo', $task->name);
    }
}
