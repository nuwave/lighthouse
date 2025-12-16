<?php declare(strict_types=1);

namespace Tests\Integration\Execution\DataLoader;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Execution\ModelsLoader\PaginatedModelsLoader;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Nuwave\Lighthouse\Pagination\PaginationType;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Tag;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class PaginatedRelationLoaderTest extends DBTestCase
{
    public function testLoadRelationshipsWithLimitsOnCollection(): void
    {
        $user1 = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user1);
        $user1->tasks()->saveMany(
            factory(Task::class, 5)->make(),
        );

        $user2 = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user2);
        $user2->tasks()->saveMany(
            factory(Task::class, 2)->make(),
        );

        $pageSize = 3;
        $users = User::all();
        (new PaginatedModelsLoader(
            'tasks',
            static function (): void {},
            $this->makePaginationArgs($pageSize),
        ))->load($users);

        $firstUser = $users[0];
        \PHPUnit\Framework\Assert::assertInstanceOf(User::class, $firstUser);
        $tasksPaginator = $firstUser->tasks;
        \PHPUnit\Framework\Assert::assertInstanceOf(LengthAwarePaginator::class, $tasksPaginator);
        $this->assertCount($pageSize, $tasksPaginator);
        $firstTask = $tasksPaginator[0];
        \PHPUnit\Framework\Assert::assertInstanceOf(Task::class, $firstTask);
        $this->assertEquals($firstUser->getKey(), $firstTask->user_id);

        $secondUser = $users[1];
        \PHPUnit\Framework\Assert::assertInstanceOf(User::class, $secondUser);
        $this->assertCount(2, $secondUser->tasks);
        $secondTask = $secondUser->tasks[0];
        \PHPUnit\Framework\Assert::assertInstanceOf(Task::class, $secondTask);
        $this->assertEquals($secondUser->getKey(), $secondTask->user_id);
    }

    public function testLoadCountOnCollection(): void
    {
        $user1 = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user1);
        $firstTasksCount = 1;
        $user1->tasks()->saveMany(
            factory(Task::class, $firstTasksCount)->make(),
        );

        $user2 = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user2);
        $secondTasksCount = 3;
        $user2->tasks()->saveMany(
            factory(Task::class, $secondTasksCount)->make(),
        );

        $users = User::all();

        (new PaginatedModelsLoader(
            'tasks',
            static function (): void {},
            $this->makePaginationArgs(10),
        ))->load($users);

        $firstUser = $users[0];
        \PHPUnit\Framework\Assert::assertInstanceOf(User::class, $firstUser);
        $this->assertSame($firstTasksCount, $firstUser->getAttributes()['tasks_count'] ?? null);

        $secondUser = $users[1];
        \PHPUnit\Framework\Assert::assertInstanceOf(User::class, $secondUser);
        $this->assertSame($secondTasksCount, $secondUser->getAttributes()['tasks_count'] ?? null);
    }

    public function testLoadsMultipleRelations(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $user->tasks()->saveMany(
            factory(Task::class, 2)->make(),
        );
        $user->posts()->saveMany(
            factory(Post::class, 3)->make(),
        );

        $users = User::all();

        (new PaginatedModelsLoader(
            'tasks',
            static function (): void {},
            $this->makePaginationArgs(4),
        ))->load($users);

        (new PaginatedModelsLoader(
            'posts',
            static function (): void {},
            $this->makePaginationArgs(4),
        ))->load($users);

        $firstUser = $users[0];
        \PHPUnit\Framework\Assert::assertInstanceOf(User::class, $firstUser);

        $this->assertTrue($firstUser->relationLoaded('tasks'));
        $this->assertTrue($firstUser->relationLoaded('posts'));
    }

    public function testHandleSoftDeletes(): void
    {
        $user1 = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user1);

        $tasksUser1 = 3;
        $user1->tasks()->saveMany(
            factory(Task::class, $tasksUser1)->make(),
        );

        $softDeletedTaskUser1 = factory(Task::class)->make();
        $user1->tasks()->save($softDeletedTaskUser1);
        $softDeletedTaskUser1->delete();

        $user2 = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user2);

        $tasksUser2 = 4;
        $user2->tasks()->saveMany(
            factory(Task::class, $tasksUser2)->make(),
        );

        $softDeletedTaskUser2 = factory(Task::class)->make();
        $user2->tasks()->save($softDeletedTaskUser2);
        $softDeletedTaskUser2->delete();

        $users = User::all();

        (new PaginatedModelsLoader(
            'tasks',
            static function (): void {},
            $this->makePaginationArgs(4),
        ))->load($users);

        $firstUser = $users[0];
        \PHPUnit\Framework\Assert::assertInstanceOf(User::class, $firstUser);
        $this->assertTrue($firstUser->relationLoaded('tasks'));
        $this->assertCount($tasksUser1, $firstUser->tasks);

        $secondUser = $users[1];
        \PHPUnit\Framework\Assert::assertInstanceOf(User::class, $secondUser);
        $this->assertTrue($secondUser->relationLoaded('tasks'));
        $this->assertCount($tasksUser2, $secondUser->tasks);
    }

    public function testGetsPolymorphicRelationship(): void
    {
        $task = factory(Task::class)->create();
        $this->assertInstanceOf(Task::class, $task);

        foreach (factory(Tag::class, 3)->make() as $tag) {
            $task->tags()->save($tag);
        }

        $this->assertCount(3, $task->tags);

        $first = 2;
        $tasks = Task::all();

        (new PaginatedModelsLoader(
            'tags',
            static function (): void {},
            $this->makePaginationArgs($first),
        ))->load($tasks);

        $firstTask = $tasks[0];
        \PHPUnit\Framework\Assert::assertInstanceOf(Task::class, $firstTask);
        $this->assertCount($first, $firstTask->tags);
    }

    private function makePaginationArgs(int $first): PaginationArgs
    {
        $paginatorArgs = new PaginationArgs(1, $first, new PaginationType('SIMPLE'));
        $paginatorArgs->first = $first;

        return $paginatorArgs;
    }
}
