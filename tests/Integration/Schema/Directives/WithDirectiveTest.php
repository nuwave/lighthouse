<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Activity;
use Tests\Utils\Models\Comment;
use Tests\Utils\Models\Image;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class WithDirectiveTest extends DBTestCase
{
    public function testEagerLoadsRelation(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User @first
        }

        type User {
            tasksLoaded: Boolean!
                @with(relation: "tasks")
                @method
        }
        ';

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        foreach (factory(Task::class, 3)->make() as $task) {
            $this->assertInstanceOf(Task::class, $task);
            $task->user()->associate($user);
            $task->save();
        }

        // Sanity check
        $this->assertFalse(
            $user->tasksLoaded(),
        );

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasksLoaded
            }
        }
        ')->assertExactJson([
            'data' => [
                'user' => [
                    'tasksLoaded' => true,
                ],
            ],
        ]);
    }

    public function testEagerLoadsNestedRelation(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: User @first
        }

        type User {
            postsCommentsLoaded: Boolean!
                @with(relation: "posts.comments")
                @method
        }
        ';

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        foreach (factory(Post::class, 2)->make() as $post) {
            $this->assertInstanceOf(Post::class, $post);
            $post->user()->associate($user);
            $post->save();

            $comment = factory(Comment::class)->make();
            $this->assertInstanceOf(Comment::class, $comment);
            $comment->user()->associate($user);
            $comment->post()->associate($post);
            $comment->save();
        }

        // Sanity check
        $this->assertFalse(
            $user->postsCommentsLoaded(),
        );

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                postsCommentsLoaded
            }
        }
        ')->assertJson([
            'data' => [
                'users' => [
                    'postsCommentsLoaded' => true,
                ],
            ],
        ]);
    }

    public function testEagerLoadsPolymorphicRelations(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            activity: [Activity!] @all
        }

        type Post {
            id: ID
            images: [Image] @with(relation: "images")
        }

        type Task {
            id: ID
            images: [Image] @with(relation: "images")
        }

        union ActivityContent = Post | Task

        type Activity {
            id: ID
            content: ActivityContent! @morphTo
        }

        type Image {
            id: ID
        }
        ';

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $post1 = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post1);
        $post1->user()->associate($user);
        $post1->save();

        $activity1 = factory(Activity::class)->make();
        $this->assertInstanceOf(Activity::class, $activity1);
        $activity1->user()->associate($user);
        $activity1->content()->associate($post1);
        $activity1->save();

        $post1->images()
            ->saveMany(
                factory(Image::class, 3)->make(),
            );

        $post2 = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post2);
        $post2->user()->associate($user);
        $post2->save();

        $activity2 = factory(Activity::class)->make();
        $this->assertInstanceOf(Activity::class, $activity2);
        $activity2->user()->associate($user);
        $activity2->content()->associate($post2);
        $activity2->save();

        $post2->images()
            ->saveMany(
                factory(Image::class, 2)->make(),
            );

        $task = $post1->task;

        $activity3 = factory(Activity::class)->make();
        $this->assertInstanceOf(Activity::class, $activity3);
        $activity3->user()->associate($user);
        $activity3->content()->associate($task);
        $activity3->save();

        $task->images()
            ->saveMany(
                factory(Image::class, 4)->make(),
            );

        $this->graphQL(/** @lang GraphQL */ '
        {
            activity {
                id
                content {
                    __typename

                    ... on Post {
                        id
                        images {
                            id
                        }
                    }

                    ... on Task {
                        id
                        images {
                            id
                        }
                    }
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'activity' => [
                    [
                        'id' => '1',
                        'content' => [
                            '__typename' => 'Post',
                            'id' => "{$post1->id}",
                            'images' => $post1->images()
                                ->get()
                                ->map(static fn (Image $image): array => ['id' => "{$image->id}"]),
                        ],
                    ],
                    [
                        'id' => '2',
                        'content' => [
                            '__typename' => 'Post',
                            'id' => "{$post2->id}",
                            'images' => $post2->images()
                                ->get()
                                ->map(static fn (Image $image): array => ['id' => "{$image->id}"]),
                        ],
                    ],
                    [
                        'id' => '3',
                        'content' => [
                            '__typename' => 'Task',
                            'id' => "{$task->id}",
                            'images' => $task->images()
                                ->get()
                                ->map(static fn (Image $image): array => ['id' => "{$image->id}"]),
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testEagerLoadsMultipleRelationsAtOnce(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: User
                @first
        }

        type User {
            tasksAndPostsCommentsLoaded: Boolean!
                @with(relation: "tasks")
                @with(relation: "posts.comments")
                @method
        }
        ';

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        foreach (factory(Task::class, 3)->make() as $task) {
            $this->assertInstanceOf(Task::class, $task);
            $task->user()->associate($user);
            $task->save();
        }

        foreach (factory(Post::class, 2)->make() as $post) {
            $this->assertInstanceOf(Post::class, $post);
            $post->user()->associate($user);
            $post->save();

            $comment = factory(Comment::class)->make();
            $this->assertInstanceOf(Comment::class, $comment);
            $comment->user()->associate($user);
            $comment->post()->associate($post);
            $comment->save();
        }

        // Sanity check
        $this->assertFalse(
            $user->tasksAndPostsCommentsLoaded(),
        );

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                tasksAndPostsCommentsLoaded
            }
        }
        ')->assertJson([
            'data' => [
                'users' => [
                    'tasksAndPostsCommentsLoaded' => true,
                ],
            ],
        ]);
    }

    public function testEagerLoadsMultipleNestedRelationsAtOnce(): void
    {
        $eloquentCollection = \Illuminate\Database\Eloquent\Collection::class;
        $simpleModelsLoader = \Nuwave\Lighthouse\Execution\ModelsLoader\SimpleModelsLoader::class;
        $this->markTestSkipped("Not working due to the current naive usage of {$eloquentCollection}::load() in {$simpleModelsLoader}::load().");

        // @phpstan-ignore-next-line unreachable due to markTestSkipped
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: User
                @first
        }

        type User {
            postTasksAndPostsCommentsLoaded: Boolean!
                @with(relation: "posts.task")
                @with(relation: "posts.comments")
                @method
        }
        ';

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $taskA = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $taskA);
        $taskA->user()->associate($user);
        $taskA->save();

        $taskB = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $taskB);
        $taskB->user()->associate($user);
        $taskB->save();

        $postA = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $postA);
        $postA->user()->associate($user);
        $postA->task()->associate($taskA);
        $postA->save();

        $postB = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $postB);
        $postB->user()->associate($user);
        $postB->task()->associate($taskB);
        $postB->save();

        foreach ([$postA, $postB] as $post) {
            $comment = factory(Comment::class)->make();
            $this->assertInstanceOf(Comment::class, $comment);
            $comment->user()->associate($user);
            $comment->post()->associate($post);
            $comment->save();
        }

        // Sanity check
        $this->assertFalse(
            $user->postTasksAndPostsCommentsLoaded(),
        );

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                postTasksAndPostsCommentsLoaded
            }
        }
        ')->assertJson([
            'data' => [
                'users' => [
                    'postTasksAndPostsCommentsLoaded' => true,
                ],
            ],
        ]);
    }

    public function testWithDirectiveOnRootFieldThrows(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            foo: Int @with(relation: "tasks")
        }
        ');
    }
}
