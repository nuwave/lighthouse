<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Comment;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class HasManyThroughDirectiveTest extends DBTestCase
{
    public function testQueryHasManyThroughRelationship(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Post {
            id: ID!
        }

        type Task {
            postComments: [Post!]! @hasManyThrough
        }

        type Query {
            task: Task! @first
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->create();
        $this->assertInstanceOf(Task::class, $task);

        $post = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post);
        $post->user()->associate($user);
        $post->task()->associate($task);
        $post->save();

        $comments = factory(Comment::class, 2)->make();
        foreach ($comments as $comment) {
            $this->assertInstanceOf(Comment::class, $comment);
            $comment->user()->associate($user);
            $comment->post()->associate($post);
            $comment->save();
        }

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            task {
                postComments {
                    id
                }
            }
        }
        GRAPHQL)->assertJsonCount(2, 'data.task.postComments');
    }
}
