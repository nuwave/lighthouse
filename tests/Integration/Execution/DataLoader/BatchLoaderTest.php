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
    public function itCanResolveBatchedFieldsFromBatchedRequests(): void
    {
        $users = factory(User::class, 2)
            ->create()
            ->each(function (User $user): void {
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

        $this
            ->postGraphQL([
                [
                    'query' => $query,
                    'variables' => [
                        'id' => $users[0]->getKey(),
                    ],
                ],
                [
                    'query' => $query,
                    'variables' => [
                        'id' => $users[1]->getKey(),
                    ],
                ],
            ])
            ->assertJsonCount(2)
            ->assertJsonCount(3, '0.data.user.tasks')
            ->assertJsonCount(3, '1.data.user.tasks');
    }
}
