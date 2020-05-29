<?php

namespace Tests\Integration\Schema\Directives;

use BadMethodCallException;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Support\AppVersion;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class WithCountDirectiveTest extends DBTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User @first
            users: [User!] @all
        }

        type User {
            completed_tasks: Int! @withCount(relation: "tasks", scopes: ["completed"])
            count_tasks: Int! @count(relation: "tasks") # Used to compare queries
            tasks_count: Int! @withCount
            no_relation: Int! @withCount # Used to prove failing test
        }
        ';
    }

    public function testEagerLoadsRelationCount(): void
    {
        if (AppVersion::below(5.7)) {
            $this->markTestSkipped('Version less than 5.7 do not support loadCount().');
        }

        /** @var \Tests\Utils\Models\User $user */
        factory(User::class, 3)->create()
            ->each(function ($user) {
                factory(Task::class, 3)->create([
                    'user_id' => $user->getKey(),
                ]);
            });

        $lazyQueries = 0;

        DB::listen(function () use (&$lazyQueries): void {
            $lazyQueries++;
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                count_tasks
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'count_tasks' => 3
                    ],
                    [
                        'count_tasks' => 3
                    ],
                    [
                        'count_tasks' => 3
                    ],
                ],
            ],
        ]);

        $this->assertEquals(4, $lazyQueries);

        $eagerQueries = 0;

        DB::listen(function () use (&$eagerQueries): void {
            $eagerQueries++;
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
                        'tasks_count' => 3
                    ],
                    [
                        'tasks_count' => 3
                    ],
                    [
                        'tasks_count' => 3
                    ],
                ],
            ],
        ]);

        $this->assertEquals(2, $eagerQueries);
        $this->assertTrue($eagerQueries < $lazyQueries);
    }

    public function testItEagerLoadsRelationCountWithScope(): void
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
                    'completed_tasks' => 1
                ],
            ],
        ]);
    }

    public function testItFailsToEagerLoadRelationCountWithoutRelation(): void
    {
        if (AppVersion::below(5.7)) {
            $this->markTestSkipped('Version less than 5.7 do not support loadCount().');
        }

        /** @var \Tests\Utils\Models\User $user */
        factory(User::class)->create();

        $this->expectException(BadMethodCallException::class);
        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                no_relation
            }
        }
        ');
    }
}
