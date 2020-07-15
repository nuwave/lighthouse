<?php

namespace Tests\Unit\Schema\Directives;

use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\AppVersion;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class CountDirectiveTest extends DBTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            completed_tasks: Int @count(model: "Task", scopes: ["completed"])
            tasks_count: Int @count(model: "Task")
            tasks: Int @count
            user: User @first
            users: [User!] @all
        }

        type User {
            completed_tasks: Int! @count(relation: "tasks", scopes: ["completed"])
            tasks: Int @count(relation: "tasks")
            tasks_count: Int @count(relation: "tasks")
            foo: Int @count
        }
        ';
    }

    public function testItRequiresARelationOrModelArgument(): void
    {
        $this->expectException(DefinitionException::class);
        $this->graphQL(/** @lang GraphQL */ '
        {
            tasks
        }
        ');
    }

    public function testItCanCountAModel(): void
    {
        factory(Task::class, 3)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            tasks_count
        }
        ')->assertExactJson([
            'data' => [
                'tasks_count' => 3,
            ],
        ]);
    }

    public function testItCanCountAModelWithScopes(): void
    {
        factory(Task::class, 3)->create();
        factory(Task::class, 2)->create([
            'completed_at' => now(),
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            completed_tasks
        }
        ')->assertExactJson([
            'data' => [
                'completed_tasks' => 2,
            ],
        ]);
    }

    public function testItCountsARelationAndEagerLoadsTheCount(): void
    {
        if (AppVersion::below(5.7)) {
            $this->markTestSkipped('Version less than 5.7 do not support loadCount().');
        }

        factory(User::class, 3)->create()
            ->each(function (User $user, int $index): void {
                factory(Task::class, 3 - $index)->create([
                    'user_id' => $user->getKey(),
                ]);
            });

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                tasks_count
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'tasks_count' => 3,
                    ],
                    [
                        'tasks_count' => 2,
                    ],
                    [
                        'tasks_count' => 1,
                    ],
                ],
            ],
        ]);

        $this->assertEquals(2, $queries);
    }

    public function testItCountsARelationThatIsNotSuffixedWithCount(): void
    {
        if (AppVersion::below(5.7)) {
            $this->markTestSkipped('Version less than 5.7 do not support loadCount().');
        }

        factory(Task::class, 3)->create([
            'user_id' => factory(User::class)->create(),
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks
            }
        }
        ')->assertExactJson([
            'data' => [
                'user' => [
                    'tasks' => 3,
                ],
            ],
        ]);
    }

    public function testItCountsARelationshipWithScopesApplied(): void
    {
        if (AppVersion::below(5.7)) {
            $this->markTestSkipped('Version less than 5.7 do not support loadCount().');
        }

        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();
        factory(Task::class, 3)->create([
            'user_id' => $user->getKey(),
        ]);

        factory(Task::class)->state('completed')->create([
            'user_id' => $user->getKey(),
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                completed_tasks
            }
        }
        ')->assertExactJson([
            'data' => [
                'user' => [
                    'completed_tasks' => 1,
                ],
            ],
        ]);
    }
}
