<?php

namespace Tests\Integration\Execution\DataLoader;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\DBTestCase;
use Tests\Utils\BatchLoaders\UserLoader;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class RelationCountBatchLoaderTest extends DBTestCase
{
    public function testResolveBatchedCountsFromBatchedRequests(): void
    {
        $users = factory(User::class, 2)
            ->create()
            ->each(function (User $user): void {
                factory(Task::class, 3)->create([
                    'user_id' => $user->getKey(),
                ]);
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
                        'user' =>  [
                            'tasks_count' => 3,
                        ],
                    ],
                ],
                [
                    'data' => [
                        'user' =>  [
                            'tasks_count' => 3,
                        ],
                    ],
                ],
            ]);
    }

    public function testResolveFieldsByCustomBatchLoader(): void
    {
        $users = factory(User::class, 3)
            ->create()
            ->each(function (User $user, int $index): void {
                factory(Task::class, $index + 1)->create([
                    'user_id' => $user->getKey(),
                ]);
            });

        $this->mockResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $info) {
                $loader = BatchLoaderRegistry::instance($info->path, function (): UserLoader {
                    return new UserLoader();
                });

                return $loader->load($args['id']);
            },
            'one'
        );
        $this->mockResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $info) {
                $loader = BatchLoaderRegistry::instance($info->path, function (): UserLoader {
                    return new UserLoader();
                });

                return $loader->loadMany($args['ids']);
            },
            'many'
        );

        $this->schema = /** @lang GraphQL */ '
        type Task {
            name: String
        }
        type User {
            name: String
            email: String
            tasks: [Task] @hasMany
            tasks_count: Int! @withCount(relation: "tasks")
        }

        type Query {
            user(id: ID!): User @mock(key: "one")
            manyUsers(ids: [ID!]!): [User!]! @mock(key: "many")
        }
        ';

        $query = /** @lang GraphQL */ '
        query ($id: ID!, $ids: [ID!]!) {
            user(id: $id) {
                email
                tasks_count
            }
            manyUsers(ids: $ids) {
                email
                tasks_count
            }
        }
        ';

        $this
            ->postGraphQL([
                'query' => $query,
                'variables' => [
                    'id' => $users[0]->getKey(),
                    'ids' => [$users[1]->getKey(), $users[2]->getKey()],
                ],
            ])
            ->assertJsonCount(2, 'data.manyUsers')
            ->assertJson([
                'data' => [
                    'manyUsers' => [
                        [
                            'tasks_count' => 2,
                        ],
                        [
                            'tasks_count' => 3,
                        ],
                    ],
                    'user' => [
                        'tasks_count' => 1,
                    ],
                ],
            ]);
    }
}
