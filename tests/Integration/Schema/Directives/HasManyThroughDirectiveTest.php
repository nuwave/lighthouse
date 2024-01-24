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
        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: ID!
        }

        type Task {
            postComments: [Post!]! @hasManyThrough
        }

        type Query {
            task: Task! @first
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $task = factory(Task::class)->create();
        assert($task instanceof Task);

        $post = factory(Post::class)->make();
        assert($post instanceof Post);
        $post->user()->associate($user);
        $post->task()->associate($task);
        $post->save();

        $comments = factory(Comment::class, 2)->make();
        foreach ($comments as $comment) {
            assert($comment instanceof Comment);
            $comment->user()->associate($user);
            $comment->post()->associate($post);
            $comment->save();
        }

        $this->graphQL(/** @lang GraphQL */ '
        {
            task {
                postComments {
                    id
                }
            }
        }
        ')->assertJsonCount(2, 'data.task.postComments');
    }
}
