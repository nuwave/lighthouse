<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class UpsertDirectiveTest extends DBTestCase
{
    public function testNestedArgResolver(): void
    {
        factory(User::class)->create();
        factory(Task::class)->create([
            'id' => 1,
            'name' => 'old',
        ]);

        $this->schema .= /** @lang GraphQL */ '
        type Mutation {
            updateUser(input: UpdateUserInput! @spread): User @update
        }
        
        type Task {
            id: Int
            name: String!
        }
        
        type User {
            name: String
            tasks: [Task!]! @hasMany
        }
        
        input UpdateUserInput {
            id: Int
            name: String
            tasks: [UpdateTaskInput!] @upsert(relation: "tasks")
        }
        
        input UpdateTaskInput {
            id: Int
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateUser(input: {
                id: 1
                name: "foo"
                tasks: [
                    {
                        id: 1
                        name: "updated"
                    }
                    {
                        id: 2
                        name: "new"
                    }
                ]
            }) {
                name
                tasks {
                    id
                    name
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'updateUser' => [
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => 1,
                            'name' => 'updated',
                        ],
                        [
                            'id' => 2,
                            'name' => 'new',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
