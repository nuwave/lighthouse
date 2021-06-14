<?php

namespace Tests\Integration\Execution\DataLoader;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\DBTestCase;
use Tests\Utils\BatchLoaders\UserLoader;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class RelationBatchLoaderTest extends DBTestCase
{
    public function testResolveBatchedFieldsFromBatchedRequests(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Task {
            id: ID
        }

        type User {
            tasks: [Task] @hasMany
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        ';

        $userCount = 2;
        $tasksPerUser = 3;
        $users = factory(User::class, $userCount)
            ->create()
            ->each(function (User $user) use ($tasksPerUser): void {
                $user->tasks()->saveMany(
                    factory(Task::class, $tasksPerUser)->make()
                );
            });

        $query = /** @lang GraphQL */ '
        query User($id: ID!) {
            user(id: $id) {
                tasks {
                    id
                }
            }
        }
        ';

        $this
            ->postGraphQL([
                [
                    'query' => $query,
                    'variables' => [
                        'id' => $users[0]->id,
                    ],
                ],
                [
                    'query' => $query,
                    'variables' => [
                        'id' => $users[1]->id,
                    ],
                ],
            ])
            ->assertJsonCount($userCount)
            ->assertJsonCount($tasksPerUser, '0.data.user.tasks')
            ->assertJsonCount($tasksPerUser, '1.data.user.tasks');
    }

    /**
     * @dataProvider batchloadRelationsSetting
     */
    public function testBatchloadRelations(bool $batchloadRelations, int $expectedQueryCount): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Task {
            id: ID
        }

        type User {
            tasks: [Task!]! @hasMany
        }

        type Query {
            users: [User!]! @all
        }
        ';

        $userCount = 2;
        $tasksPerUser = 3;
        factory(User::class, $userCount)
            ->create()
            ->each(function (User $user) use ($tasksPerUser): void {
                $user->tasks()->saveMany(
                    factory(Task::class, $tasksPerUser)->make()
                );
            });

        config(['lighthouse.batchload_relations' => $batchloadRelations]);

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                users {
                    tasks {
                        id
                    }
                }
            }
            ')
            ->assertJsonCount($userCount, 'data.users')
            ->assertJsonCount($tasksPerUser, 'data.users.0.tasks')
            ->assertJsonCount($tasksPerUser, 'data.users.1.tasks');

        $this->assertSame($expectedQueryCount, $queryCount);
    }

    /**
     * @return array<array<bool|int>>
     */
    public function batchloadRelationsSetting(): array
    {
        return [
            [true, 2],
            [false, 3],
        ];
    }

    public function testCombineEagerLoadsThatAreTheSame(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Task {
            id: ID
        }

        type User {
            name: String @with(relation: "tasks")
            tasks: [Task!]! @hasMany
        }

        type Query {
            users: [User!]! @all
        }
        ';

        factory(User::class, 2)->create();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                tasks {
                    id
                }
            }
        }
        ');
        $this->assertSame(2, $queryCount);

        $queryCount = 0;
        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                name
            }
        }
        ');
        // @phpstan-ignore-next-line $queryCount is modified
        $this->assertSame(2, $queryCount);

        $queryCount = 0;
        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                name
                tasks {
                    id
                }
            }
        }
        ');
        // @phpstan-ignore-next-line $queryCount is modified
        $this->assertSame(2, $queryCount);
    }

    public function testSplitsEagerLoadsByScopes(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Task {
            id: ID
        }

        type User {
            name: String @with(relation: "tasks")
            tasks: [Task!]! @hasMany(scopes: ["completed"])
        }

        type Query {
            users: [User!]! @all
        }
        ';

        factory(User::class, 2)->create();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                name
                tasks {
                    id
                }
            }
        }
        ');
        $this->assertSame(3, $queryCount);
    }

    public function testSplitsEagerLoadsWithArguments(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Task {
            id: ID
        }

        type User {
            name: String @with(relation: "tasks")
            tasks(name: String @eq): [Task!]! @hasMany
        }

        type Query {
            users: [User!]! @all
        }
        ';

        factory(User::class, 2)->create();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                name
                tasks(name: "Prevents combination of eager loads") {
                    id
                }
            }
        }
        ');
        $this->assertSame(3, $queryCount);
    }

    public function testResolveFieldsByCustomBatchLoader(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Task {
            name: String
        }
        type User {
            name: String
            email: String
            tasks: [Task] @hasMany
        }

        type Query {
            user(id: ID!): User @mock(key: "one")
            manyUsers(ids: [ID!]!): [User!]! @mock(key: "many")
        }
        ';

        $users = factory(User::class, 3)
            ->create()
            ->each(function (User $user): void {
                factory(Task::class, 3)->create([
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

        $query = /** @lang GraphQL */ '
        query User($id: ID!, $ids: [ID!]!) {
            user(id: $id) {
                email
                tasks {
                    name
                }
            }
            manyUsers(ids: $ids) {
                email
                tasks {
                    name
                }
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
            ->assertJsonCount(3, 'data.manyUsers.0.tasks')
            ->assertJsonCount(3, 'data.manyUsers.1.tasks')
            ->assertJsonCount(3, 'data.user.tasks');
    }

    public function testTwoBatchLoadedQueriesWithDifferentResults(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Task {
            id: ID
        }

        type User {
            tasks: [Task] @hasMany
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        ';

        factory(User::class, 2)
            ->create()
            ->each(function (User $user): void {
                $user->tasks()->saveMany(
                    factory(Task::class, 3)->make()
                );
            });

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                user(id: 1) {
                    tasks {
                        id
                    }
                }
            }
            ')
            ->assertJson([
                'data' => [
                    'user' => [
                        'tasks' => [
                            [
                                'id' => '1',
                            ],
                            [
                                'id' => '2',
                            ],
                            [
                                'id' => '3',
                            ],
                        ],
                    ],
                ],
            ]);

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                user(id: 2) {
                    tasks {
                        id
                    }
                }
            }
            ')
            ->assertJson([
                'data' => [
                    'user' => [
                        'tasks' => [
                            [
                                'id' => '4',
                            ],
                            [
                                'id' => '5',
                            ],
                            [
                                'id' => '6',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function testCombineEagerLoadsThatAreTheSameRecursively(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            task(id: Int! @eq): Task @find
        }

        type Task {
            post: Post! @hasOne
            name: String! @with(relation: "post.user")
        }

        type Post {
            user: User! @belongsTo
        }

        type User {
            id: ID!
        }
        ';

        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();

        /** @var \Tests\Utils\Models\Task $task */
        $task = factory(Task::class)->make();
        $task->user()->associate($user);
        $task->save();

        /** @var \Tests\Utils\Models\Post $post */
        $post = factory(Post::class)->make();
        $post->task()->associate($task);
        $post->user()->associate($user);
        $post->save();

        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($id: Int!) {
                task(id: $id) {
                    name
                    post {
                        user {
                            id
                        }
                    }
                }
            }
            ', [
                'id' => $task->id,
            ])
            ->assertJson([
                'data' => [
                    'task' => [
                        'name' => $task->name,
                        'post' => [
                            'user' => [
                                'id' => (string) $user->id,
                            ],
                        ],
                    ],
                ],
            ]);

        // TODO optimize this
        $this->markTestIncomplete('The intermediary relation of dot notation is not batched with equivalent relations of fields.');
        // @phpstan-ignore-next-line Of course this terminates...
        $this->assertSame(3, $queries);
    }
}
