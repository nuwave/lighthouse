<?php

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Activity;
use Tests\Utils\Models\Comment;
use Tests\Utils\Models\Image;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class WithDirectiveTest extends DBTestCase
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

        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();
        factory(Task::class, 3)->create([
            'user_id' => $user->getKey(),
        ]);

        // Sanity check
        $this->assertFalse(
            $user->tasksLoaded()
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

        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();
        $posts = factory(Post::class, 2)->create([
            'user_id' => $user->id,
        ]);
        foreach ($posts as $post) {
            factory(Comment::class)->create([
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);
        }

        // Sanity check
        $this->assertFalse(
            $user->postsCommentsLoaded()
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

        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();

        /** @var \Tests\Utils\Models\Post $post1 */
        $post1 = factory(Post::class)->make();
        $user->posts()->save($post1);

        /** @var \Tests\Utils\Models\Activity $activity1 */
        $activity1 = factory(Activity::class)->make();
        $activity1->user()->associate($user);
        $post1->activity()->save($activity1);

        $post1->images()
            ->saveMany(
                factory(Image::class, 3)->make()
            );

        /** @var \Tests\Utils\Models\Post $post2 */
        $post2 = factory(Post::class)->make();
        $user->posts()->save($post2);

        /** @var \Tests\Utils\Models\Activity $activity2 */
        $activity2 = factory(Activity::class)->make();
        $activity2->user()->associate($user);
        $post2->activity()->save($activity2);

        $post2->images()
            ->saveMany(
                factory(Image::class, 2)->make()
            );

        $task = $post1->task;

        /** @var \Tests\Utils\Models\Activity $activity3 */
        $activity3 = factory(Activity::class)->make();
        $activity3->user()->associate($user);
        $task->activity()->save($activity3);

        $task->images()
            ->saveMany(
                factory(Image::class, 4)->make()
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
                            'images' => $post1->images()->get()
                                ->map(function (Image $image) {
                                    return  ['id' => "{$image->id}"];
                                }),
                        ],
                    ],
                    [
                        'id' => '2',
                        'content' => [
                            '__typename' => 'Post',
                            'id' => "{$post2->id}",
                            'images' => $post2->images()->get()
                                ->map(function (Image $image) {
                                    return  ['id' => "{$image->id}"];
                                }),
                        ],
                    ],
                    [
                        'id' => '3',
                        'content' => [
                            '__typename' => 'Task',
                            'id' => "{$task->id}",
                            'images' => $task->images()->get()
                                ->map(static function (Image $image): array {
                                    return  ['id' => "{$image->id}"];
                                }),
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

        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();
        factory(Task::class, 3)->create([
            'user_id' => $user->getKey(),
        ]);

        $posts = factory(Post::class, 2)->create([
            'user_id' => $user->id,
        ]);
        foreach ($posts as $post) {
            factory(Comment::class)->create([
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);
        }

        // Sanity check
        $this->assertFalse(
            $user->tasksAndPostsCommentsLoaded()
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
