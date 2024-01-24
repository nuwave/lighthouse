<?php declare(strict_types=1);

namespace Tests\Integration\OrderBy;

use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class OrderByDirectiveDBTest extends DBTestCase
{
    protected string $schema = /** @lang GraphQL */ '
    type Query {
        users(
            orderBy: _ @orderBy
            orderByRestricted: _ @orderBy(columns: ["name"])
            orderByRestrictedEnum: _ @orderBy(columnsEnum: "UserColumn")
        ): [User!]! @all
    }

    type User {
        name: String
        team_id: Int
    }

    enum UserColumn {
        NAME @enum(value: "name")
    }
    ';

    public function testOrderByTheGivenColumnAndSortOrderASC(): void
    {
        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderBy: [
                    {
                        column: "name"
                        order: ASC
                    }
                ]
            ) {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'name' => 'A',
                    ],
                    [
                        'name' => 'B',
                    ],
                ],
            ],
        ]);
    }

    public function testOrderByTheGivenFieldAndSortOrderDESC(): void
    {
        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderBy: [
                    {
                        column: "name"
                        order: DESC
                    }
                ]
            ) {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'name' => 'B',
                    ],
                    [
                        'name' => 'A',
                    ],
                ],
            ],
        ]);
    }

    public function testOrderByMultipleColumns(): void
    {
        factory(User::class)->create(['name' => 'B', 'team_id' => 2]);
        factory(User::class)->create(['name' => 'A', 'team_id' => 5]);
        factory(User::class)->create(['name' => 'C', 'team_id' => 2]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderBy: [
                    {
                        column: "team_id"
                        order: ASC
                    }
                    {
                        column: "name"
                        order: ASC
                    }
                ]
            ) {
                team_id
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'team_id' => 2,
                        'name' => 'B',
                    ],
                    [
                        'team_id' => 2,
                        'name' => 'C',
                    ],
                    [
                        'team_id' => 5,
                        'name' => 'A',
                    ],
                ],
            ],
        ]);
    }

    public function testOrderWithRestrictedColumns(): void
    {
        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderByRestricted: [
                    {
                        column: NAME
                        order: ASC
                    }
                ]
            ) {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'name' => 'A',
                    ],
                    [
                        'name' => 'B',
                    ],
                ],
            ],
        ]);
    }

    public function testUseColumnEnumsArg(): void
    {
        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderByRestrictedEnum: [
                    {
                        column: NAME
                        order: ASC
                    }
                ]
            ) {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'name' => 'A',
                    ],
                    [
                        'name' => 'B',
                    ],
                ],
            ],
        ]);
    }

    public function testRejectsDefinitionWithDuplicateColumnArgument(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            users(
                orderBy: _ @orderBy(columns: ["name"], columnsEnum: "UserColumn")
            ): [User!]! @all
        }

        type User {
            name: String
            team_id: Int
        }

        enum UserColumn {
            NAME @enum(value: "name")
        }
        ');
    }

    public function testOrderColumnOnField(): void
    {
        $now = Carbon::now();
        factory(User::class)->create(['name' => 'A']);

        Carbon::setTestNow($now->addYear());
        factory(User::class)->create(['name' => 'B']);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            latestUsers: [User!]!
                @all
                @orderBy(column: "created_at" direction: DESC)
        }

        type User {
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            latestUsers {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'latestUsers' => [
                    [
                        'name' => 'B',
                    ],
                    [
                        'name' => 'A',
                    ],
                ],
            ],
        ]);
    }

    public function testOrderByRelationCount(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users(
                orderBy: _ @orderBy(relations: [
                    {
                        relation: "tasks"
                    }
                ])
            ): [User!]! @all
        }

        type User {
            id: Int!
        }
        ';

        $userA = factory(User::class)->create();
        assert($userA instanceof User);

        $userB = factory(User::class)->create();
        assert($userB instanceof User);

        $userA->tasks()->saveMany(
            factory(Task::class, 1)->create(),
        );
        $userB->tasks()->saveMany(
            factory(Task::class, 2)->create(),
        );

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderBy: [
                    {
                        tasks: { aggregate: COUNT }
                        order: DESC
                    }
                ]
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'id' => $userB->id,
                    ],
                    [
                        'id' => $userA->id,
                    ],
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderBy: [
                    {
                        tasks: { aggregate: COUNT }
                        order: ASC
                    }
                ]
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'id' => $userA->id,
                    ],
                    [
                        'id' => $userB->id,
                    ],
                ],
            ],
        ]);
    }

    public function testOrderByRelationAggregate(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users(
                orderBy: _ @orderBy(relations: [
                    {
                        relation: "tasks"
                        columns: ["difficulty"]
                    }
                ])
            ): [User!]! @all
        }

        type User {
            id: Int!
        }

        enum UserColumn {
            NAME @enum(value: "name")
        }
        ';

        $userA = factory(User::class)->create();
        assert($userA instanceof User);

        $userB = factory(User::class)->create();
        assert($userB instanceof User);

        $taskA1 = factory(Task::class)->make();
        assert($taskA1 instanceof Task);
        $taskA1->difficulty = 1;
        $userA->tasks()->save($taskA1);

        $taskB1 = factory(Task::class)->make();
        assert($taskB1 instanceof Task);
        $taskB1->difficulty = 2;
        $userB->tasks()->save($taskB1);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderBy: [
                    {
                        tasks: { aggregate: SUM, column: DIFFICULTY }
                        order: DESC
                    }
                ]
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'id' => $userB->id,
                    ],
                    [
                        'id' => $userA->id,
                    ],
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderBy: [
                    {
                        tasks: { aggregate: SUM, column: DIFFICULTY }
                        order: ASC
                    }
                ]
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'id' => $userA->id,
                    ],
                    [
                        'id' => $userB->id,
                    ],
                ],
            ],
        ]);
    }
}
