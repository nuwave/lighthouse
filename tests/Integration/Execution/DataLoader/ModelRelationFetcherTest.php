<?php

namespace Tests\Integration\Execution\DataLoader;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Execution\DataLoader\ModelRelationFetcher;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Tests\DBTestCase;
use Tests\Utils\Models\Tag;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class ModelRelationFetcherTest extends DBTestCase
{
    public function testCanLoadRelationshipsWithLimitsOnCollection(): void
    {
        /** @var \Tests\Utils\Models\User $user1 */
        $user1 = factory(User::class)->create();
        $user1->tasks()->saveMany(
            factory(Task::class, 5)->make()
        );

        /** @var \Tests\Utils\Models\User $user2 */
        $user2 = factory(User::class)->create();
        $user2->tasks()->saveMany(
            factory(Task::class, 2)->make()
        );

        $pageSize = 3;
        $users = (new ModelRelationFetcher(User::all(), ['tasks']))
            ->loadRelationsForPage($this->makePaginationArgs($pageSize));

        $firstResult = $users[0];
        /** @var \Illuminate\Pagination\LengthAwarePaginator $tasksPaginator */
        $tasksPaginator = $firstResult->tasks;
        $this->assertInstanceOf(LengthAwarePaginator::class, $tasksPaginator);
        $this->assertSame($pageSize, $tasksPaginator->count());
        $this->assertEquals($firstResult->getKey(), $tasksPaginator[0]->user_id);

        $secondResult = $users[1];
        $this->assertSame(2, $secondResult->tasks->count());
        $this->assertEquals($secondResult->getKey(), $secondResult->tasks[0]->user_id);
    }

    public function testCanLoadCountOnCollection(): void
    {
        /** @var \Tests\Utils\Models\User $user1 */
        $user1 = factory(User::class)->create();
        $user1->tasks()->saveMany(
            factory(Task::class, 4)->make()
        );

        /** @var \Tests\Utils\Models\User $user2 */
        $user2 = factory(User::class)->create();
        $user2->tasks()->saveMany(
            factory(Task::class, 5)->make()
        );

        $users = (new ModelRelationFetcher(User::all(), ['tasks']))
            ->reloadModelsWithRelationCount();

        $this->assertEquals($users[0]->tasks()->count(), 4);
        $this->assertEquals($users[1]->tasks_count, 5);
    }

    public function testCanHandleSoftDeletes(): void
    {
        $initialCount = 4;

        /** @var \Tests\Utils\Models\User $user1 */
        $user = factory(User::class)->create();
        $user->tasks()->saveMany(
            factory(Task::class, $initialCount)->make()
        );

        Task::first()->delete();

        $users = (new ModelRelationFetcher(User::all(), ['tasks']))
            ->loadRelationsForPage($this->makePaginationArgs(4));

        $this->assertCount($initialCount - 1, $users[0]->tasks);
    }

    public function testGetsPolymorphicRelationship(): void
    {
        /** @var Task $task */
        $task = factory(Task::class)->create();
        $task->tags()->saveMany(
            factory(Tag::class, 3)->make()
        );

        $this->assertCount(3, $task->tags);

        $first = 2;
        $tasks = (new ModelRelationFetcher(Task::all(), ['tags']))
            ->loadRelationsForPage($this->makePaginationArgs($first));

        $this->assertCount($first, $tasks->first()->tags);
    }

    protected function makePaginationArgs(int $first)
    {
        $paginatorArgs = new PaginationArgs();
        $paginatorArgs->first = $first;

        return $paginatorArgs;
    }
}
