<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\AppVersion;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class AggregateDirectiveTest extends DBTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if (AppVersion::below(8.0)) {
            $this->markTestSkipped('Eager aggregate loading is only available in Laravel 8+.');
        }
    }

    public function testRequiresARelationOrModelArgument(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            tasks: Int @aggregate
        }
        ';

        $this->expectException(DefinitionException::class);
        $this->graphQL(/** @lang GraphQL */ '
        {
            tasks
        }
        ');
    }

    public function testAggregateModel(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            sum: Int! @aggregate(model: "Task", function: SUM, column: "difficulty")
            avg: Float! @aggregate(model: "Task", function: AVG, column: "difficulty")
            min: Int! @aggregate(model: "Task", function: MIN, column: "difficulty")
            max: Int! @aggregate(model: "Task", function: MAX, column: "difficulty")
        }
        ';

        $tasks = factory(Task::class, 3)->create();

        $response = $this->graphQL(/** @lang GraphQL */ '
        {
            sum
            avg
            min
            max
        }
        ');

        $response->assertJson([
            'data' => [
                'sum' => $tasks->sum('difficulty'),
                'min' => $tasks->min('difficulty'),
                'max' => $tasks->max('difficulty'),
            ],
        ]);

        $this->assertEqualsWithDelta(
            $tasks->avg('difficulty'),
            $response->json('data.avg'),
            0.01
        );
    }

    public function testSumModelWithScopes(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            finished: Int! @aggregate(model: "Task", function: SUM, column: "difficulty", scopes: ["completed"])
        }
        ';

        factory(Task::class, 3)->create();
        $completed = factory(Task::class, 2)->create([
            'completed_at' => now(),
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            finished
        }
        ')->assertExactJson([
            'data' => [
                'finished' => $completed->sum('difficulty'),
            ],
        ]);
    }

    public function testSumRelationEagerLoad(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: [User!] @all
        }

        type User {
            workload: Int! @aggregate(relation: "tasks", function: SUM, column: "difficulty")
        }
        ';

        factory(User::class, 3)
            ->create()
            ->each(function (User $user, int $index): void {
                /** @var \Tests\Utils\Models\Task $task */
                $task = factory(Task::class)->make();
                $task->difficulty = $index;
                $task->user()->associate($user);
                $task->save();
            });

        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            ++$queries;
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                workload
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'workload' => 0,
                    ],
                    [
                        'workload' => 1,
                    ],
                    [
                        'workload' => 2,
                    ],
                ],
            ],
        ]);

        $this->assertSame(2, $queries);
    }

    public function testSumRelationWithScopes(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User @first
        }

        type User {
            finished: Int! @aggregate(relation: "tasks", function: SUM, column: "difficulty", scopes: ["completed"])
        }
        ';

        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();

        $ongoing = factory(Task::class)->make();
        $user->tasks()->save($ongoing);

        /** @var \Tests\Utils\Models\Task $completed */
        $completed = factory(Task::class)->state('completed')->make();
        $user->tasks()->save($completed);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                finished
            }
        }
        ')->assertExactJson([
            'data' => [
                'user' => [
                    'finished' => $completed->difficulty,
                ],
            ],
        ]);
    }

    public function testMultipleAggregatesOnSameRelationWithAliases(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            difficulty(
                minimum: Int @where(key: "difficulty", operator: ">=")
                maximum: Int @where(key: "difficulty", operator: "<=")
            ): Int! @aggregate(relation: "tasks", function: SUM, column: "difficulty")
        }

        type Query {
            users: [User!]! @all
        }
        ';

        /** @var \Tests\Utils\Models\User $user1 */
        $user1 = factory(User::class)->create();

        $low1 = factory(Task::class)->make();
        $low1->difficulty = 42;
        $user1->tasks()->save($low1);

        $high1 = factory(Task::class)->make();
        $high1->difficulty = 9001;
        $user1->tasks()->save($high1);

        /** @var \Tests\Utils\Models\User $user2 */
        $user2 = factory(User::class)->create();

        $low2 = factory(Task::class)->make();
        $low2->difficulty = 69;
        $user2->tasks()->save($low2);

        $high2 = factory(Task::class)->make();
        $high2->difficulty = 9002;
        $user2->tasks()->save($high2);

        $this
            ->graphQL(/** @lang GraphQL */ '
            query {
                users {
                    lowDifficulty: difficulty(maximum: 9000)
                    highDifficulty: difficulty(minimum: 9000)
                }
            }
            ')
            ->assertExactJson([
                'data' => [
                    'users' => [
                        [
                            'lowDifficulty' => 42,
                            'highDifficulty' => 9001,
                        ],
                        [
                            'lowDifficulty' => 69,
                            'highDifficulty' => 9002,
                        ],
                    ],
                ],
            ]);
    }
}
