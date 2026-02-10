<?php declare(strict_types=1);

namespace Tests\Integration\Execution\DataLoader;

use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\Cache\CacheKeyAndTagsGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\AlternateConnection;
use Tests\Utils\Models\Comment;
use Tests\Utils\Models\NullConnection;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class RelationBatchLoaderTest extends DBTestCase
{
    public function testResolveBatchedFieldsFromBatchedRequests(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID
        }

        type User {
            tasks: [Task] @hasMany
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        $userCount = 2;
        $tasksPerUser = 3;
        $users = factory(User::class, $userCount)
            ->create()
            ->each(static function (User $user) use ($tasksPerUser): void {
                $user->tasks()->saveMany(
                    factory(Task::class, $tasksPerUser)->make(),
                );
            });

        $query = /** @lang GraphQL */ <<<'GRAPHQL'
        query User($id: ID!) {
            user(id: $id) {
                tasks {
                    id
                }
            }
        }
        GRAPHQL;

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

    /** @dataProvider batchloadRelationsSetting */
    #[DataProvider('batchloadRelationsSetting')]
    public function testBatchloadRelations(bool $batchloadRelations, int $expectedQueryCount): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID
        }

        type User {
            tasks: [Task!]! @hasMany
        }

        type Query {
            users: [User!]! @all
        }
        GRAPHQL;

        $userCount = 2;
        $tasksPerUser = 3;
        factory(User::class, $userCount)
            ->create()
            ->each(static function (User $user) use ($tasksPerUser): void {
                $user->tasks()->saveMany(
                    factory(Task::class, $tasksPerUser)->make(),
                );
            });

        config(['lighthouse.batchload_relations' => $batchloadRelations]);

        $this->assertQueryCountMatches($expectedQueryCount, function () use ($userCount, $tasksPerUser): void {
            $this
                ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                {
                    users {
                        tasks {
                            id
                        }
                    }
                }
                GRAPHQL)
                ->assertJsonCount($userCount, 'data.users')
                ->assertJsonCount($tasksPerUser, 'data.users.0.tasks')
                ->assertJsonCount($tasksPerUser, 'data.users.1.tasks');
        });
    }

    public function testDoesNotBatchloadRelationsWithDifferentDatabaseConnections(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type AlternateConnection {
            id: ID
        }

        type User {
            alternateConnections: [AlternateConnection!]! @hasMany
        }

        type Query {
            users: [User!]! @all
        }
        GRAPHQL;

        $userCount = 2;
        $alternateConnectionsPerUser = 3;
        factory(User::class, $userCount)
            ->create()
            ->each(static function (User $user) use ($alternateConnectionsPerUser): void {
                $user->alternateConnections()->saveMany(
                    factory(AlternateConnection::class, $alternateConnectionsPerUser)->make(),
                );
            });

        $this->countQueries($queryCount);

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users {
                    alternateConnections {
                        id
                    }
                }
            }
            GRAPHQL)
            ->assertJsonCount($userCount, 'data.users')
            ->assertJsonCount($alternateConnectionsPerUser, 'data.users.0.alternateConnections')
            ->assertJsonCount($alternateConnectionsPerUser, 'data.users.1.alternateConnections');

        $this->assertSame(3, $queryCount);
    }

    public function testDoesNotBatchloadRelationsWithNullDatabaseConnections(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type NullConnection {
            users: [User!]! @hasMany
        }

        type User {
            id: ID
        }

        type Query {
            nullConnections: [NullConnection!]! @all
        }
        GRAPHQL;

        $nullConnectionsCount = 2;
        $usersPerNullConnection = 3;
        factory(NullConnection::class, $nullConnectionsCount)
            ->create()
            ->each(static function (NullConnection $nullConnection) use ($usersPerNullConnection): void {
                $nullConnection->users()->saveMany(
                    factory(User::class, $usersPerNullConnection)->make(),
                );
            });

        config(['lighthouse.batchload_relations' => true]);

        $this->assertQueryCountMatches(2, function () use ($nullConnectionsCount, $usersPerNullConnection): void {
            $this
                ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                {
                    nullConnections {
                        users {
                            id
                        }
                    }
                }
                GRAPHQL)
                ->assertJsonCount($nullConnectionsCount, 'data.nullConnections')
                ->assertJsonCount($usersPerNullConnection, 'data.nullConnections.0.users')
                ->assertJsonCount($usersPerNullConnection, 'data.nullConnections.1.users');
        });
    }

    /** @return iterable<array{bool, int}> */
    public static function batchloadRelationsSetting(): iterable
    {
        yield [true, 2];
        yield [false, 3];
    }

    public function testCombineEagerLoadsThatAreTheSame(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        factory(User::class, 2)->create();

        $this->assertQueryCountMatches(2, function (): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users {
                    tasks {
                        id
                    }
                }
            }
            GRAPHQL);
        });

        $this->assertQueryCountMatches(2, function (): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users {
                    name
                }
            }
            GRAPHQL);
        });

        $this->assertQueryCountMatches(2, function (): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users {
                    name
                    tasks {
                        id
                    }
                }
            }
            GRAPHQL);
        });
    }

    public function testSplitsEagerLoadsByScopes(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        factory(User::class, 2)->create();

        $this->assertQueryCountMatches(3, function (): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users {
                    name
                    tasks {
                        id
                    }
                }
            }
            GRAPHQL);
        });
    }

    public function testSplitsEagerLoadsWithArguments(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        factory(User::class, 2)->create();

        $this->assertQueryCountMatches(3, function (): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users {
                    name
                    tasks(name: "Prevents combination of eager loads") {
                        id
                    }
                }
            }
            GRAPHQL);
        });
    }

    public function testTwoBatchLoadedQueriesWithDifferentResults(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID
        }

        type User {
            tasks: [Task] @hasMany
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        factory(User::class, 2)
            ->create()
            ->each(static function (User $user): void {
                $user->tasks()->saveMany(
                    factory(Task::class, 3)->make(),
                );
            });

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                user(id: 1) {
                    tasks {
                        id
                    }
                }
            }
            GRAPHQL)
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
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                user(id: 2) {
                    tasks {
                        id
                    }
                }
            }
            GRAPHQL)
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

    /** @return never */
    public function testCombineEagerLoadsThatAreTheSameRecursively(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $post = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post);
        $post->task()->associate($task);
        $post->user()->associate($user);
        $post->save();

        $this->countQueries($queryCount);

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
            GRAPHQL, [
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
        $this->assertSame(3, $queryCount);
    }

    public function testBatchLoaderWithExpiredCacheEntry(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            posts: [Post!]! @all @cache(maxAge: 20)
        }

        type Post {
            id: ID!
            comments: [Comment!]! @hasMany @cache(maxAge: 20)
        }

        type Comment {
            id: ID!
            user: User! @belongsTo
        }

        type User {
            id: ID!
        }
        GRAPHQL;

        $user1 = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user1);

        $post1 = factory(Post::class)->create();
        $this->assertInstanceOf(Post::class, $post1);

        $comments1 = factory(Comment::class, 3)->make();
        foreach ($comments1 as $comment) {
            $this->assertInstanceOf(Comment::class, $comment);
            $comment->user()->associate($user1);
            $comment->post()->associate($post1);
            $comment->save();
        }

        $user2 = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user2);

        $post2 = factory(Post::class)->create();
        $this->assertInstanceOf(Post::class, $post2);

        $comments2 = factory(Comment::class, 3)->make();
        foreach ($comments2 as $comment) {
            $this->assertInstanceOf(Comment::class, $comment);
            $comment->user()->associate($user2);
            $comment->post()->associate($post2);
            $comment->save();
        }

        $firstRequest = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query {
            posts {
                comments {
                    user {
                        id
                    }
                }
            }
        }
        GRAPHQL);

        Cache::forget(
            (new CacheKeyAndTagsGenerator())->key(
                user: null,
                isPrivate: false,
                parentName: 'Post',
                id: $post2->id,
                fieldName: 'comments',
                args: [],
                path: ['posts', $post2->id, 'comments'],
            ),
        );

        $secondRequest = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query {
            posts {
                comments {
                    user {
                        id
                    }
                }
            }
        }
        GRAPHQL);

        $this->assertSame($firstRequest->json(), $secondRequest->json());
    }
}
