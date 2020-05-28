<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class WithCountDirectiveTest extends DBTestCase
{
    public function testEagerLoadsRelationCount(): void
    {
        if (AppVersion::below(5.7)) {
            $this->markTestSkipped('Version less than 5.7 do not support loadCount().');
        }

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User @first
        }

        type User {
            tasks_count: Int! @withCount
            tasks_relation_count: Int! @withCount(relation: "tasks")
            tasksCount: Int! @withCount(relation: "tasks")
        }
        ';

        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();
        factory(Task::class, 3)->create([
            'user_id' => $user->getKey(),
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks_count
                tasks_relation_count
                tasksCount
            }
        }
        ')->assertExactJson([
            'data' => [
                'user' => [
                    'tasks_count' => 3,
                    'tasks_relation_count' => 3,
                    'tasksCount' => 3,
                ],
            ],
        ]);
    }
}
