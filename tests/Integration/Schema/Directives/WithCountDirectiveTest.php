<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
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
}
