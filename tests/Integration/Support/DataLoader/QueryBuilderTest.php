<?php

namespace Nuwave\Lighthouse\Tests\Integration\Support\DataLoader;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Tests\DBTestCase;
use Nuwave\Lighthouse\Tests\Utils\Models\Task;
use Nuwave\Lighthouse\Tests\Utils\Models\User;

class QueryBuilderTest extends DBTestCase
{
    use RefreshDatabase;

    /**
     * Setup test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $users = factory(User::class, 3)->create();
        $users->each(function ($user) {
            factory(Task::class, 5)->create([
                'user_id' => $user->getKey(),
            ]);
        });
    }

    /**
     * @test
     */
    public function itCanPaginateCollectionOfRelationships()
    {
        $users = User::all();
        $users->fetch(['tasks' => function ($q) {
            $q->take(3);
        }]);

        $this->assertCount(3, $users[0]->tasks);
        $this->assertCount(3, $users[1]->tasks);
        $this->assertCount(3, $users[2]->tasks);
        $this->assertEquals($users[0]->getKey(), $users[0]->tasks->first()->user_id);
        $this->assertEquals($users[1]->getKey(), $users[1]->tasks->first()->user_id);
        $this->assertEquals($users[2]->getKey(), $users[2]->tasks->first()->user_id);
    }
}
