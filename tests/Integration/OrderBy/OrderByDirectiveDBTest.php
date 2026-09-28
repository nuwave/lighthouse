<?php declare(strict_types=1);

namespace Tests\Integration\OrderBy;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

final class OrderByDirectiveDBTest extends DBTestCase
{
    protected string $schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
    GRAPHQL;

    public function testOrderByTheGivenColumnAndSortOrderASC(): void
    {
        $this->createUser('B');
        $this->createUser('A');

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertExactJson([
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
        $this->createUser('B');
        $this->createUser('A');

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertExactJson([
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
        $teamA = factory(Team::class)->create();
        $this->assertInstanceOf(Team::class, $teamA);

        $teamB = factory(Team::class)->create();
        $this->assertInstanceOf(Team::class, $teamB);

        $this->createUser('B', $teamA);
        $this->createUser('A', $teamB);
        $this->createUser('C', $teamA);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'team_id' => $teamA->id,
                        'name' => 'B',
                    ],
                    [
                        'team_id' => $teamA->id,
                        'name' => 'C',
                    ],
                    [
                        'team_id' => $teamB->id,
                        'name' => 'A',
                    ],
                ],
            ],
        ]);
    }

    public function testOrderWithRestrictedColumns(): void
    {
        $this->createUser('B');
        $this->createUser('A');

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertExactJson([
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
        $this->createUser('B');
        $this->createUser('A');

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertExactJson([
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

        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL);
    }

    public function testOrderColumnOnField(): void
    {
        $userA = factory(User::class)->make();
        $userA->name = 'A';
        $userA->save();

        $this->travel(1)->year();

        $userB = factory(User::class)->make();
        $userB->name = 'B';
        $userB->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            latestUsers: [User!]!
                @all
                @orderBy(column: "created_at" direction: DESC)
        }

        type User {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            latestUsers {
                name
            }
        }
        GRAPHQL)->assertExactJson([
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
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $userA = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $userA);

        $userB = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $userB);

        $userA->tasks()->saveMany(
            factory(Task::class, 1)->create(),
        );
        $userB->tasks()->saveMany(
            factory(Task::class, 2)->create(),
        );

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertExactJson([
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

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertExactJson([
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
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $userA = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $userA);

        $userB = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $userB);

        $taskA1 = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $taskA1);
        $taskA1->difficulty = 1;
        $userA->tasks()->save($taskA1);

        $taskB1 = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $taskB1);
        $taskB1->difficulty = 2;
        $userB->tasks()->save($taskB1);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertExactJson([
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

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertExactJson([
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

    private function createUser(string $name, ?Team $team = null): User
    {
        $user = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user);
        $user->name = $name;
        if ($team === null) {
            $user->team()->dissociate();
        } else {
            $user->team()->associate($team);
        }

        $user->save();

        return $user;
    }
}
