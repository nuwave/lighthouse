<?php declare(strict_types=1);

namespace Tests\Integration\Execution\DataLoader;

use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\Cache\CacheKeyAndTagsGenerator;
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
            ->each(static function (User $user) use ($tasksPerUser): void {
                $user->tasks()->saveMany(
                    factory(Task::class, $tasksPerUser)->make(),
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

    /** @dataProvider batchloadRelationsSetting */
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
            ->each(static function (User $user) use ($tasksPerUser): void {
                $user->tasks()->saveMany(
                    factory(Task::class, $tasksPerUser)->make(),
                );
            });

        config(['lighthouse.batchload_relations' => $batchloadRelations]);

        $this->assertQueryCountMatches($expectedQueryCount, function () use ($userCount, $tasksPerUser): void {
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
        });
    }

    public function testDoesNotBatchloadRelationsWithDifferentDatabaseConnections(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type AlternateConnection {
            id: ID
        }

        type User {
            alternateConnections: [AlternateConnection!]! @hasMany
        }

        type Query {
            users: [User!]! @all
        }
        ';

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
            ->graphQL(/** @lang GraphQL */ '
            {
                users {
                    alternateConnections {
                        id
                    }
                }
            }
            ')
            ->assertJsonCount($userCount, 'data.users')
            ->assertJsonCount($alternateConnectionsPerUser, 'data.users.0.alternateConnections')
            ->assertJsonCount($alternateConnectionsPerUser, 'data.users.1.alternateConnections');

        $this->assertSame(3, $queryCount);
    }

    public function testDoesNotBatchloadRelationsWithNullDatabaseConnections(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type NullConnection {
            users: [User!]! @hasMany
        }

        type User {
            id: ID
        }

        type Query {
            nullConnections: [NullConnection!]! @all
        }
        ';

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
                ->graphQL(/** @lang GraphQL */ '
                {
                    nullConnections {
                        users {
                            id
                        }
                    }
                }
                ')
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

        $this->assertQueryCountMatches(2, function (): void {
            $this->graphQL(/** @lang GraphQL */ '
            {
                users {
                    tasks {
                        id
                    }
                }
            }
            ');
        });

        $this->assertQueryCountMatches(2, function (): void {
            $this->graphQL(/** @lang GraphQL */ '
            {
                users {
                    name
                }
            }
            ');
        });

        $this->assertQueryCountMatches(2, function (): void {
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
        });
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

        $this->assertQueryCountMatches(3, function (): void {
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
        });
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

        $this->assertQueryCountMatches(3, function (): void {
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
        });
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
            ->each(static function (User $user): void {
                $user->tasks()->saveMany(
                    factory(Task::class, 3)->make(),
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

    /** @return never */
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

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $task = factory(Task::class)->make();
        assert($task instanceof Task);
        $task->user()->associate($user);
        $task->save();

        $post = factory(Post::class)->make();
        assert($post instanceof Post);
        $post->task()->associate($task);
        $post->user()->associate($user);
        $post->save();

        $this->countQueries($queryCount);

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
        $this->assertSame(3, $queryCount);
    }

    public function testBatchLoaderWithExpiredCacheEntry(): void
    {
        $this->schema = /** @lang GraphQL */ '
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
        ';

        $user1 = factory(User::class)->create();
        assert($user1 instanceof User);

        $post1 = factory(Post::class)->create();
        assert($post1 instanceof Post);

        $comments1 = factory(Comment::class, 3)->make();
        foreach ($comments1 as $comment) {
            assert($comment instanceof Comment);
            $comment->user()->associate($user1);
            $comment->post()->associate($post1);
            $comment->save();
        }

        $user2 = factory(User::class)->create();
        assert($user2 instanceof User);

        $post2 = factory(Post::class)->create();
        assert($post2 instanceof Post);

        $comments2 = factory(Comment::class, 3)->make();
        foreach ($comments2 as $comment) {
            assert($comment instanceof Comment);
            $comment->user()->associate($user2);
            $comment->post()->associate($post2);
            $comment->save();
        }

        $firstRequest = $this->graphQL(/** @lang GraphQL */ '
        query {
            posts {
                comments {
                    user {
                        id
                    }
                }
            }
        }
        ');

        Cache::forget(
            (new CacheKeyAndTagsGenerator())->key(null, false, 'Post', $post2->id, 'comments', [], ['posts', $post2->id, 'comments']),
        );

        $secondRequest = $this->graphQL(/** @lang GraphQL */ '
        query {
            posts {
                comments {
                    user {
                        id
                    }
                }
            }
        }
        ');

        $this->assertSame($firstRequest->json(), $secondRequest->json());
    }
}
