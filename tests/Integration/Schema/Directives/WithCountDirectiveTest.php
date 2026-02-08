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
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: [User!] @all
        }

        type User {
            tasksCountLoaded: Boolean!
                @withCount(relation: "tasks")
                @method
        }
        ';

        factory(User::class, 3)->create()
            ->each(static function (User $user): void {
                $tasks = factory(Task::class, 3)->create();
                $tasks->each(static function (Task $task) use ($user): void {
                    $task->user()->associate($user);
                    $task->save();
                });
            });

        $this->assertQueryCountMatches(2, function (): void {
            $this->graphQL(/** @lang GraphQL */ '
            {
                users {
                    tasksCountLoaded
                }
            }
            ')->assertExactJson([
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
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: [User!] @all
        }

        type User {
            name: String! @withCount
        }
        ';

        factory(User::class)->create();

        $this->expectException(DefinitionException::class);
        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                name
            }
        }
        ');
    }
}
