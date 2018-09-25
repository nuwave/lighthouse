<?php

namespace Tests\Integration\Support\DataLoader;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QueryBuilderTest extends DBTestCase
{
    /**
     * Setup test environment.
     */
    public function setUp()
    {
        parent::setUp();
        
        $count = 4;
        $users = factory(User::class, 3)->create();
        $users->each(function ($user) use (&$count) {
            factory(Task::class, $count)->create([
                'user_id' => $user->getKey(),
            ]);
            ++$count;
        });
    }

    /**
     * @test
     */
    public function itCanLoadRelationshipsWithLimitsOnCollection()
    {
        $users = User::all();
        // TODO remove this as soon as Laravel fixes https://github.com/laravel/framework/issues/16217
        $users->fetch(['tasks' => function ($query) {
            $query->take(3);
        }]);

        $this->assertCount(3, $users[0]->tasks);
        $this->assertCount(3, $users[1]->tasks);
        $this->assertCount(3, $users[2]->tasks);
        $this->assertEquals($users[0]->getKey(), $users[0]->tasks->first()->user_id);
        $this->assertEquals($users[1]->getKey(), $users[1]->tasks->first()->user_id);
        $this->assertEquals($users[2]->getKey(), $users[2]->tasks->first()->user_id);
    }

    /**
     * @test
     */
    public function itCanLoadCountOnCollection()
    {
        $users = User::all();
        $users->fetchCount(['tasks']);

        $this->assertEquals($users[0]->tasks_count, 4);
        $this->assertEquals($users[1]->tasks_count, 5);
        $this->assertEquals($users[2]->tasks_count, 6);
    }

    /**
     * @test
     */
    public function itCanPaginateRelationshipOnCollection()
    {
        $users = User::all();
        $users->fetchForPage(2, 1, ['tasks']);

        $this->assertInstanceOf(LengthAwarePaginator::class, $users[0]->tasks);
        $this->assertInstanceOf(LengthAwarePaginator::class, $users[1]->tasks);
        $this->assertInstanceOf(LengthAwarePaginator::class, $users[2]->tasks);
        $this->assertCount(2, $users[0]->tasks);
        $this->assertCount(2, $users[1]->tasks);
        $this->assertCount(2, $users[2]->tasks);
        $this->assertEquals($users[0]->getKey(), $users[0]->tasks[0]->user_id);
        $this->assertEquals($users[1]->getKey(), $users[1]->tasks[0]->user_id);
        $this->assertEquals($users[2]->getKey(), $users[2]->tasks[0]->user_id);
    }

    /**
     * @test
     */
    public function itCanHandleSoftDeletes()
    {
        $user = User::first();
        $count = $user->tasks()->count();
        $task = $user->tasks()->get()->last();
        $task->delete();

        $users = User::all();
        $users->fetch(['tasks' => function ($query) use ($count) {
            $query->take($count);
        }]);

        $expectedCount = $count - 1;
        $this->assertCount($expectedCount, $users[0]->tasks);
    }
}
