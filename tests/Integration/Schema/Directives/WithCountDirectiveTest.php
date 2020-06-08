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
            tasks_count: Int! @withCount
            count_tasks: Int! @withCount # Used to prove failing test
        }
        ';
    }

    public function testEagerLoadsRelationCount(): void
    {
        if (AppVersion::below(5.7)) {
            $this->markTestSkipped('Version less than 5.7 do not support loadCount().');
        }

        factory(User::class, 3)->create()
            ->each(function ($user) {
                factory(Task::class, 3)->create([
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
                        'tasks_count' => 3,
                    ],
                    [
                        'tasks_count' => 3,
                    ],
                ],
            ],
        ]);

        $this->assertEquals(2, $queries);
    }

    public function testItFailsToEagerLoadRelationCountWithoutRelation(): void
    {
        if (AppVersion::below(5.7)) {
            $this->markTestSkipped('Version less than 5.7 do not support loadCount().');
        }

        factory(User::class)->create();

        $this->expectException(BadMethodCallException::class);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                count_tasks
            }
        }
        ');
    }
}
