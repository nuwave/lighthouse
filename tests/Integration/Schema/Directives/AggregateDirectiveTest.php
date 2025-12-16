<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class AggregateDirectiveTest extends DBTestCase
{
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
            0.01,
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
            ->each(static function (User $user, int $index): void {
                $task = factory(Task::class)->make();
                \PHPUnit\Framework\Assert::assertInstanceOf(Task::class, $task);
                $task->difficulty = $index;
                $task->user()->associate($user);
                $task->save();
            });

        $this->assertQueryCountMatches(2, function (): void {
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
        });
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

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $ongoing = factory(Task::class)->make();
        $user->tasks()->save($ongoing);

        $completed = factory(Task::class)->state('completed')->make();
        $this->assertInstanceOf(Task::class, $completed);
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

        $user1 = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user1);

        $low1 = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $low1);
        $low1->difficulty = 42;
        $user1->tasks()->save($low1);

        $high1 = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $high1);
        $high1->difficulty = 9001;
        $user1->tasks()->save($high1);

        $user2 = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user2);

        $low2 = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $low2);
        $low2->difficulty = 69;
        $user2->tasks()->save($low2);

        $high2 = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $high2);
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

    public function testAggregateWithBuilder(): void
    {
        $this->schema = /** @lang GraphQL */ "
        type Query {
            sum(
                difficulty: Int! @eq
                exclude: ID!
            ): Int! @aggregate(builder: \"{$this->qualifyTestResolver('builder')}\", function: SUM, column: \"difficulty\")
        }
        ";

        $difficulty = 5;

        $task1 = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task1);
        $task1->difficulty = 3;
        $task1->save();

        $task2 = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task2);
        $task2->difficulty = $difficulty;
        $task2->save();

        $task3 = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task3);
        $task3->difficulty = $difficulty;
        $task3->save();

        $task4 = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task4);
        $task4->difficulty = $difficulty;
        $task4->save();

        $this->graphQL(/** @lang GraphQL */ '
        query ($difficulty: Int!, $exclude: ID!) {
            sum(difficulty: $difficulty, exclude: $exclude)
        }
        ', [
            'difficulty' => $difficulty,
            'exclude' => $task4->id,
        ])->assertJson([
            'data' => [
                'sum' => $difficulty * 2,
            ],
        ]);
    }

    /** @param  array{difficulty: int, exclude: int}  $args */
    public function builder(mixed $root, array $args): Builder
    {
        return DB::table('tasks')
            ->where('id', '!=', $args['exclude']);
    }
}
