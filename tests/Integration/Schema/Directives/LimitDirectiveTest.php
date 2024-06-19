<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Cache\CacheKeyAndTagsGenerator;
use Tests\DBTestCase;
use Tests\TestsSerialization;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class LimitDirectiveTest extends DBTestCase
{
    use TestsSerialization;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $this->useSerializingArrayStore();
    }

    public function testLimitsResults(): void
    {
        factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(limit: Int @limit): [User!]! @all
        }
        ';

        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $limit = 1;
        $this->graphQL(/** @lang GraphQL */ '
        query ($limit: Int) {
            users(limit: $limit) {
                id
            }
        }
        ', [
            'limit' => $limit,
        ])->assertJsonCount($limit, 'data.users');
        $this->assertSame([
            'select * from `users`',
        ], $queries);
    }

    public function testLimitsQueryBuilder(): void
    {
        factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(limit: Int @limit(builder: true)): [User!]! @all
        }
        ';

        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $limit = 1;
        $this->graphQL(/** @lang GraphQL */ '
        query ($limit: Int) {
            users(limit: $limit) {
                id
            }
        }
        ', [
            'limit' => $limit,
        ])->assertJsonCount($limit, 'data.users');
        $this->assertSame([
            "select * from `users` limit {$limit}",
        ], $queries);
    }

    /**
     * TODO support this and add INPUT_FIELD_DEFINITION back as an allowed location.
     *
     * @return never
     */
    public function testLimitOnInputField(): void
    {
        $this->markTestSkipped('Not implemented yet because a naive implementation would cause a performance hit on all fields.');

        // @phpstan-ignore-next-line https://github.com/phpstan/phpstan-phpunit/issues/52
        factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        input UserFilter {
            limit: Int @limit
        }

        type Query {
            users(filter: UserFilter): [User!]! @all
        }
        ';

        $limit = 1;
        $this->graphQL(/** @lang GraphQL */ '
        query ($limit: Int) {
            users(filter: {
                limit: $limit
            }) {
                id
            }
        }
        ', [
            'limit' => $limit,
        ])->assertJsonCount($limit, 'data.users');
    }

    public function testLimitsRelations(): void
    {
        $users = factory(User::class, 2)->create();

        foreach ($users as $user) {
            assert($user instanceof User);
            $user->tasks()->saveMany(
                factory(Task::class, 2)->make(),
            );
        }

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            tasks(limit: Int @limit): [Task!]! @hasMany
        }

        type Task {
            id: ID!
        }

        type Query {
            users: [User!]! @all
        }
        ';

        $limit = 1;
        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($limit: Int) {
                users {
                    id
                    tasks(limit: $limit) {
                        id
                    }
                }
            }
            ', [
                'limit' => $limit,
            ])
            ->assertJsonCount($limit, 'data.users.0.tasks')
            ->assertJsonCount($limit, 'data.users.1.tasks');
    }

    public function testLimitsWithCache(): void
    {
        $user1 = factory(User::class)->create();
        $user2 = factory(User::class)->create();

        foreach ([$user1, $user2] as $user) {
            assert($user instanceof User);
            $user->tasks()->saveMany(
                factory(Task::class, 2)->make(),
            );
        }

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID! @cacheKey
            tasks(limit: Int @limit): [Task!]! @hasMany @cache
        }

        type Task {
            id: ID!
        }

        type Query {
            user: [User!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                user {
                    id
                    tasks(limit: 1) {
                        id
                    }
                }
            }
            ');

        $cache = $this->app->make(CacheRepository::class);

        $data = $cache->get(
            (new CacheKeyAndTagsGenerator())->key(null, false, 'User', $user2->id, 'tasks', ['limit' => 1], ['user', $user2->id, 'tasks']),
        );
        $this->assertIsArray($data);

        $task = $data[0];
        $this->assertInstanceOf(Task::class, $task);
        $this->assertSame(3, $task->id);

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                user {
                    id
                    tasks(limit: 1) {
                        id
                    }
                }
            }
            ')
            ->assertJson([
                'data' => [
                    'user' => [
                        [
                            'id' => 1,
                            'tasks' => [
                                [
                                    'id' => 1,
                                ],
                            ],
                        ],
                        [
                            'id' => 2,
                            'tasks' => [
                                [
                                    'id' => 3,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }
}
