<?php

namespace Tests\Integration\Execution\DataLoader;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Execution\DataLoader\PaginatedRelationLoader;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Tag;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class PaginatedRelationLoaderTest extends DBTestCase
{
    public function testLoadRelationshipsWithLimitsOnCollection(): void
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
        $users = User::all();
        (new PaginatedRelationLoader(
            static function () {
            },
            $this->makePaginationArgs($pageSize)
        ))->load($users, 'tasks');

        /** @var \Tests\Utils\Models\User $firstUser */
        $firstUser = $users[0];
        /** @var \Illuminate\Pagination\LengthAwarePaginator<\Tests\Utils\Models\Task> $tasksPaginator */
        $tasksPaginator = $firstUser->tasks;
        $this->assertInstanceOf(LengthAwarePaginator::class, $tasksPaginator);
        $this->assertCount($pageSize, $tasksPaginator);
        /** @var \Tests\Utils\Models\Task $firstTask */
        $firstTask = $tasksPaginator[0];
        $this->assertEquals($firstUser->getKey(), $firstTask->user_id);

        /** @var \Tests\Utils\Models\User $secondUser */
        $secondUser = $users[1];
        $this->assertCount(2, $secondUser->tasks);
        /** @var \Tests\Utils\Models\Task $secondTask */
        $secondTask = $secondUser->tasks[0];
        $this->assertEquals($secondUser->getKey(), $secondTask->user_id);
    }

    public function testLoadCountOnCollection(): void
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

        $users = User::all();

        (new PaginatedRelationLoader(
            static function () {
            },
            $this->makePaginationArgs(10)
        ))->load($users, 'tasks');

        /** @var \Tests\Utils\Models\User $firstUser */
        $firstUser = $users[0];
        $this->assertSame($firstTasksCount, $firstUser->getAttributes()['tasks_count'] ?? null);

        /** @var \Tests\Utils\Models\User $secondUser */
        $secondUser = $users[1];
        $this->assertSame($secondTasksCount, $secondUser->getAttributes()['tasks_count'] ?? null);
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

        $users = User::all();

        (new PaginatedRelationLoader(
            static function () {
            },
            $this->makePaginationArgs(4)
        ))->load($users, 'tasks');

        (new PaginatedRelationLoader(
            static function () {
            },
            $this->makePaginationArgs(4)
        ))->load($users, 'posts');

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
        $user1->tasks()->saveMany(
            factory(Task::class, $tasksUser1)->make()
        );

        $softDeletedTaskUser1 = factory(Task::class)->make();
        $user1->tasks()->save($softDeletedTaskUser1);
        $softDeletedTaskUser1->delete();

        /** @var \Tests\Utils\Models\User $user2 */
        $user2 = factory(User::class)->create();

        $tasksUser2 = 4;
        $user2->tasks()->saveMany(
            factory(Task::class, $tasksUser2)->make()
        );

        $softDeletedTaskUser2 = factory(Task::class)->make();
        $user2->tasks()->save($softDeletedTaskUser2);
        $softDeletedTaskUser2->delete();

        $users = User::all();

        (new PaginatedRelationLoader(
            static function () {
            },
            $this->makePaginationArgs(4)
        ))->load($users, 'tasks');

        /** @var \Tests\Utils\Models\User $firstUser */
        $firstUser = $users[0];
        $this->assertTrue($firstUser->relationLoaded('tasks'));
        $this->assertCount($tasksUser1, $firstUser->tasks);

        /** @var \Tests\Utils\Models\User $secondUser */
        $secondUser = $users[1];
        $this->assertTrue($secondUser->relationLoaded('tasks'));
        $this->assertCount($tasksUser2, $secondUser->tasks);
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
        $tasks = Task::all();

        (new PaginatedRelationLoader(
            static function () {
            },
            $this->makePaginationArgs($first)
        ))->load($tasks, 'tags');

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
