<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\AppVersion;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class WithCountDirectiveTest extends DBTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (AppVersion::below(5.7)) {
            $this->markTestSkipped('Version less than 5.7 do not support loadCount().');
        }
    }

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

        $this->assertSame(2, $queries);
    }

    public function testItFailsToEagerLoadRelationCountWithoutRelation(): void
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
