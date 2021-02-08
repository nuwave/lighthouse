<?php

namespace Tests\Integration\Execution\DataLoader;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\DBTestCase;
use Tests\Utils\BatchLoaders\UserLoader;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class RelationBatchLoaderTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Task {
        id: ID
        name: String
    }

    type User {
        name: String
        email: String
        tasks: [Task] @hasMany
    }

    type Query {
        user(id: ID! @eq): User @find
        users: [User!]! @all
    }
    ';

    /** @var \Illuminate\Support\Collection<User> */
    protected $users;

    public function setUp(): void
    {
        parent::setUp();

        $this->users = factory(User::class, 2)
            ->create()
            ->each(function (User $user): void {
                factory(Task::class, 3)->create([
                    'user_id' => $user->getKey(),
                ]);
            });
    }

    public function testCanResolveBatchedFieldsFromBatchedRequests(): void
    {
        $query = /** @lang GraphQL */ '
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
                        'id' => $this->users[0]->getKey(),
                    ],
                ],
                [
                    'query' => $query,
                    'variables' => [
                        'id' => $this->users[1]->getKey(),
                    ],
                ],
            ])
            ->assertJsonCount(2)
            ->assertJsonCount(3, '0.data.user.tasks')
            ->assertJsonCount(3, '1.data.user.tasks');
    }

    /**
     * @dataProvider batchloadRelationsSetting
     */
    public function testBatchloadRelations(bool $batchloadRelations, int $expectedQueryCount): void
    {
        config(['lighthouse.batchload_relations' => $batchloadRelations]);

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                users {
                    email
                    tasks {
                        name
                    }
                }
            }
            ')
            ->assertJsonCount(2, 'data.users')
            ->assertJsonCount(3, 'data.users.1.tasks')
            ->assertJsonCount(3, 'data.users.0.tasks');

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

    public function testCanResolveFieldsByCustomBatchLoader(): void
    {
        $users = factory(User::class, 3)
            ->create()
            ->each(function (User $user): void {
                factory(Task::class, 3)->create([
                    'user_id' => $user->getKey(),
                ]);
            });

        $this->mockResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $info) {
                $loader = BatchLoaderRegistry::instance(UserLoader::class, $info->path);

                return $loader->load($args['id']);
            },
            'one'
        );
        $this->mockResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $info) {
                $loader = BatchLoaderRegistry::instance(UserLoader::class, $info->path);

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
        }

        type Query {
            user(id: ID!): User @mock(key: "one")
            manyUsers(ids: [ID!]!): [User!]! @mock(key: "many")
        }
        ';

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

    public function testTwoBatchloadedQueriesWithDifferentResults(): void
    {
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
            ->assertExactJson([
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
            ->assertExactJson([
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
}
