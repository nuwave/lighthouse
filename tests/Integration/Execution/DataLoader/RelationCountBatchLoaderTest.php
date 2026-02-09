<?php declare(strict_types=1);

namespace Tests\Integration\Execution\DataLoader;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class RelationCountBatchLoaderTest extends DBTestCase
{
    public function testResolveBatchedCountsFromBatchedRequests(): void
    {
        $users = factory(User::class, 2)
            ->create()
            ->each(static function (User $user): void {
                $tasks = factory(Task::class, 3)->make();
                $tasks->each(static function (Task $task) use ($user): void {
                    $task->user()->associate($user);
                    $task->save();
                });
            });

        $this->schema = /** @lang GraphQL */ '
        type Task {
            id: ID
            name: String
        }

        type User {
            name: String
            email: String
            tasks: [Task] @hasMany
            tasks_count: Int! @withCount(relation: "tasks")
        }

        type Query {
            user(id: ID! @eq): User @find
            users: [User!]! @all
        }
        ';

        $query = /** @lang GraphQL */ '
        query ($id: ID!) {
            user(id: $id) {
                tasks_count
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
            ->assertJson([
                [
                    'data' => [
                        'user' => [
                            'tasks_count' => 3,
                        ],
                    ],
                ],
                [
                    'data' => [
                        'user' => [
                            'tasks_count' => 3,
                        ],
                    ],
                ],
            ]);
    }
}
