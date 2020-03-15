<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Comment;
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

        $this->assertFalse(
            $user->postsCommentsLoaded()
        );

        $response = $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                postsCommentsLoaded
            }
        }
        ');

        $response->assertJson([
            'data' => [
                'users' => [
                    'postsCommentsLoaded' => true,
                ],
            ],
        ]);
    }
}
