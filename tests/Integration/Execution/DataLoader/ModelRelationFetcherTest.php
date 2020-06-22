<?php

namespace Tests\Integration\Execution\DataLoader;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Execution\DataLoader\ModelRelationFetcher;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
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
            ->loadRelationsForPage(
                $this->makePaginationArgs($pageSize)
            );

        /** @var \Tests\Utils\Models\User $firstUser */
        $firstUser = $users[0];
        /** @var \Illuminate\Pagination\LengthAwarePaginator<\Tests\Utils\Models\Task> $tasksPaginator */
        $tasksPaginator = $firstUser->tasks;
        $this->assertInstanceOf(LengthAwarePaginator::class, $tasksPaginator);
        $this->assertSame($pageSize, $tasksPaginator->count());
        /** @var \Tests\Utils\Models\Task $firstTask */
        $firstTask = $tasksPaginator[0];
        $this->assertEquals($firstUser->getKey(), $firstTask->user_id);

        /** @var \Tests\Utils\Models\User $secondUser */
        $secondUser = $users[1];
        $this->assertSame(2, $secondUser->tasks->count());
        /** @var \Tests\Utils\Models\Task $secondTask */
        $secondTask = $secondUser->tasks[0];
        $this->assertEquals($secondUser->getKey(), $secondTask->user_id);
    }

    public function testCanLoadCountOnCollection(): void
    {
        /** @var \Tests\Utils\Models\User $user1 */
        $user1 = factory(User::class)->create();
        $firstTasksCount = 1;
        $user1->tasks()->saveMany(
            factory(Task::class, $firstTasksCount)->make()
        );

        /** @var \Tests\Utils\Models\User $user2 */
        $user2 = factory(User::class)->create();
        $secondTasksCount = 3;
        $user2->tasks()->saveMany(
            factory(Task::class, $secondTasksCount)->make()
        );

        $users = (new ModelRelationFetcher(User::all(), ['tasks']))
            ->reloadModelsWithRelationCount();

        /** @var \Tests\Utils\Models\User $firstUser */
        $firstUser = $users[0];
        $this->assertSame($firstTasksCount, $firstUser->tasks()->count());
        /** @var \Tests\Utils\Models\User $secondUser */
        $secondUser = $users[1];
        $this->assertSame($secondTasksCount, $secondUser->tasks->count());
    }

    public function testLoadsMultipleRelations(): void
    {
        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();

        $user->tasks()->saveMany(
            factory(Task::class, 2)->make()
        );
        $user->posts()->saveMany(
            factory(Post::class, 3)->make()
        );

        $users = (new ModelRelationFetcher(User::all(), ['tasks', 'posts']))
            ->loadRelationsForPage($this->makePaginationArgs(4));

        /** @var \Tests\Utils\Models\User $firstUser */
        $firstUser = $users[0];

        $this->assertTrue($firstUser->relationLoaded('tasks'));
        $this->assertTrue($firstUser->relationLoaded('posts'));
    }

    public function testCanHandleSoftDeletes(): void
    {
        /** @var \Tests\Utils\Models\User $user1 */
        $user1 = factory(User::class)->create();

        $tasksUser1 = 3;
        /** @var \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Task> $tasks1 */
        $tasks1 = $user1->tasks()->saveMany(
            factory(Task::class, $tasksUser1)->make()
        );
        $tasks1[0]->delete();

        /** @var \Tests\Utils\Models\User $user2 */
        $user2 = factory(User::class)->create();

        /** @var \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Task> $tasks2 */
        $tasksUser2 = 4;
        $tasks2 = $user2->tasks()->saveMany(
            factory(Task::class, $tasksUser2)->make()
        );
        $tasks2[0]->delete();

        $users = (new ModelRelationFetcher(User::all(), ['tasks']))
            ->loadRelationsForPage($this->makePaginationArgs(4));

        /** @var \Tests\Utils\Models\User $firstUser */
        $firstUser = $users[0];
        $this->assertTrue($firstUser->relationLoaded('tasks'));
        $this->assertCount($tasksUser1 - 1, $firstUser->tasks);

        /** @var \Tests\Utils\Models\User $secondUser */
        $secondUser = $users[1];
        $this->assertTrue($secondUser->relationLoaded('tasks'));
        $this->assertCount($tasksUser2 - 1, $secondUser->tasks);
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
            ->loadRelationsForPage(
                $this->makePaginationArgs($first)
            );

        /** @var \Tests\Utils\Models\Task $firstTask */
        $firstTask = $tasks[0];
        $this->assertCount($first, $firstTask->tags);
    }

    protected function makePaginationArgs(int $first): PaginationArgs
    {
        $paginatorArgs = new PaginationArgs();
        $paginatorArgs->first = $first;

        return $paginatorArgs;
    }
}
