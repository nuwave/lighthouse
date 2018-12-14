<?php

namespace Tests\Integration\Execution\DataLoader;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class BatchLoaderTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanResolveBatchedFieldsFromBatchedRequests()
    {
        $users = factory(User::class, 2)
            ->create()
            ->each(function (User $user) {
                factory(Task::class, 3)->create([
                    'user_id' => $user->getKey(),
                ]);
            });

        $this->schema = '
        type Task {
            name: String
        }
        type User {
            name: String
            email: String
            tasks: [Task] @hasMany
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        ';

        $query = '
        query User($id: ID!) {
            user(id: $id) {
                email
                tasks {
                    name
                }
            }
        }
        ';

        $batchedQueries = [
            ['query' => $query, 'variables' => ['id' => $users[0]->getKey()]],
            ['query' => $query, 'variables' => ['id' => $users[1]->getKey()]],
        ];

        $data = $this->postJson('/graphql', $batchedQueries)->json();
        $this->assertCount(2, $data);
        $this->assertCount(3, array_get($data, '0.data.user.tasks'));
        $this->assertCount(3, array_get($data, '1.data.user.tasks'));
    }
}
