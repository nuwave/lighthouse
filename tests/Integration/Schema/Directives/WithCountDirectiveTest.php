<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class WithCountDirectiveTest extends DBTestCase
{
    public function testEagerLoadsRelationCount(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!] @all
        }

        type User {
            tasksCountLoaded: Boolean!
                @withCount(relation: "tasks")
                @method
        }
        GRAPHQL;

        factory(User::class, 3)->create()
            ->each(static function (User $user): void {
                $tasks = factory(Task::class, 3)->make();
                $tasks->each(static function (Task $task) use ($user): void {
                    $task->user()->associate($user);
                    $task->save();
                });
            });

        $this->assertQueryCountMatches(2, function (): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users {
                    tasksCountLoaded
                }
            }
            GRAPHQL)->assertExactJson([
                'data' => [
                    'users' => [
                        [
                            'tasksCountLoaded' => true,
                        ],
                        [
                            'tasksCountLoaded' => true,
                        ],
                        [
                            'tasksCountLoaded' => true,
                        ],
                    ],
                ],
            ]);
        });
    }

    public function testFailsToEagerLoadRelationCountWithoutRelation(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!] @all
        }

        type User {
            name: String! @withCount
        }
        GRAPHQL;

        factory(User::class)->create();

        $this->expectException(DefinitionException::class);
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users {
                name
            }
        }
        GRAPHQL);
    }

    public function testEagerLoadsRelationCountOntoEveryInstanceOfTheSameModel(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            roles: [Role!]! @all
        }

        type Role {
            users: [User!]! @belongsToMany
        }

        type User {
            tasksCountLoaded: Boolean!
                @withCount(relation: "tasks")
                @method
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        // The same user under two roles is hydrated as two separate instances
        $user->roles()->attach(factory(Role::class, 2)->create());

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            roles {
                users {
                    tasksCountLoaded
                }
            }
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'roles' => [
                    [
                        'users' => [
                            ['tasksCountLoaded' => true],
                        ],
                    ],
                    [
                        'users' => [
                            ['tasksCountLoaded' => true],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
