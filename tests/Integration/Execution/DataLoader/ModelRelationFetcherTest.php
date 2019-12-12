<?php

namespace Tests\Integration\Execution\DataLoader;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Execution\DataLoader\ModelRelationFetcher;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Tests\DBTestCase;
use Tests\Utils\Models\Tag;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class ModelRelationFetcherTest extends DBTestCase
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
        $first = 3;
        $users = (new ModelRelationFetcher(User::all(), ['tasks']))
            ->loadRelationsForPage($this->makePaginationArgs($first))
            ->models();

        $this->assertCount($first, $users[0]->tasks->getCollection());
        $this->assertCount($first, $users[1]->tasks->getCollection());
        $this->assertCount($first, $users[2]->tasks->getCollection());
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
        $first = 2;
        $users = (new ModelRelationFetcher(User::all(), ['tasks']))
            ->loadRelationsForPage($this->makePaginationArgs($first))
            ->models();

        $this->assertInstanceOf(LengthAwarePaginator::class, $users[0]->tasks);
        $this->assertInstanceOf(LengthAwarePaginator::class, $users[1]->tasks);
        $this->assertInstanceOf(LengthAwarePaginator::class, $users[2]->tasks);
        $this->assertCount($first, $users[0]->tasks);
        $this->assertCount($first, $users[1]->tasks);
        $this->assertCount($first, $users[2]->tasks);
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
            ->loadRelationsForPage($this->makePaginationArgs($count))
            ->models();

        $expectedCount = $count - 1;
        $this->assertCount($expectedCount, $users[0]->tasks);
    }

    public function testGetsPolymorphicRelationship(): void
    {
        $task = factory(Task::class)->create();
        $tags = factory(Tag::class, 3)->create();

        $tags->each(function (Tag $tag) use ($task): void {
            DB::table('taggables')->insert([
                'tag_id' => $tag->id,
                'taggable_id' => $task->id,
                'taggable_type' => get_class($task),
            ]);
        });

        /** @var \Tests\Utils\Models\Task $task */
        $task = Task::first();
        $this->assertCount(3, $task->tags);

        $first = 2;
        $tasks = (new ModelRelationFetcher(Task::all(), ['tags']))
            ->loadRelationsForPage($this->makePaginationArgs($first))
            ->models();

        $this->assertCount($first, $tasks->first()->tags);
    }

    protected function makePaginationArgs(int $first)
    {
        $paginatorArgs = new PaginationArgs();
        $paginatorArgs->first = $first;

        return $paginatorArgs;
    }
}
