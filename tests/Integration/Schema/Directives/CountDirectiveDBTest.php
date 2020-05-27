<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class CountDirectiveDBTest extends DBTestCase
{
    public function testCanResolveCountByModel(): void
    {
        factory(User::class)->times(3)->create();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: Int! @count(model: "User")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users
        }
        ')->assertJson([
            'data' => [
                'users' => 3,
            ],
        ]);
    }

    public function testCanResolveCountByRelation(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();

        $user->tasks()->saveMany(
            factory(Task::class)->times(4)->create()
        );

        $this->be($user);

        $this->schema = /** @lang GraphQL */ '
        type User {
            taskCount: Int! @count(relation: "tasks")
        }

        type Query {
            user: User @auth
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                taskCount
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'taskCount' => 4,
                ],
            ],
        ]);
    }
}
