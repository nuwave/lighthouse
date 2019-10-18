<?php

namespace Tests\Integration\Support\DataLoader;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Execution\DataLoader\ModelRelationFetcher;

class ModelRelationLoaderTest extends DBTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $count = 4;
        $users = factory(User::class, 3)->create();
        $users->each(function (User $user) use (&$count): void {
            factory(Task::class, $count)->create([
                'user_id' => $user->getKey(),
            ]);
            $count++;
        });
    }

    public function testCanLoadRelationshipsWithLimitsOnCollection(): void
    {
        // TODO refactor this as soon as Laravel fixes https://github.com/laravel/framework/issues/16217

        $users = (new ModelRelationFetcher(User::all(), ['tasks']))
            ->loadRelationsForPage(3)
            ->models();

        $this->assertCount(3, $users[0]->tasks->getCollection());
        $this->assertCount(3, $users[1]->tasks->getCollection());
        $this->assertCount(3, $users[2]->tasks->getCollection());
        $this->assertEquals($users[0]->getKey(), $users[0]->tasks[0]->user_id);
        $this->assertEquals($users[1]->getKey(), $users[1]->tasks[0]->user_id);
        $this->assertEquals($users[2]->getKey(), $users[2]->tasks[0]->user_id);
    }

    public function testCanLoadCountOnCollection(): void
    {
        $users = (new ModelRelationFetcher(User::all(), ['tasks']))
            ->reloadModelsWithRelationCount()
            ->models();

        $this->assertEquals($users[0]->tasks_count, 4);
        $this->assertEquals($users[1]->tasks_count, 5);
        $this->assertEquals($users[2]->tasks_count, 6);
    }

    public function testCanPaginateRelationshipOnCollection(): void
    {
        $users = (new ModelRelationFetcher(User::all(), ['tasks']))
            ->loadRelationsForPage(2)
            ->models();

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

    public function testCanHandleSoftDeletes(): void
    {
        $user = User::first();
        $count = $user->tasks->count();
        $task = $user->tasks->last();
        $task->delete();

        $users = (new ModelRelationFetcher(User::all(), ['tasks']))
            ->loadRelationsForPage($count)
            ->models();

        $expectedCount = $count - 1;
        $this->assertCount($expectedCount, $users[0]->tasks);
    }
}
