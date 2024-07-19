<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\PostStatus;

final class HasOneThroughDirectiveTest extends DBTestCase
{
    public function testQueryHasOneThroughRelationship(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            tasks: [Task!]! @all
        }

        type Task {
            id: ID!
            postStatus: PostStatus @hasOneThrough
        }

        type PostStatus {
            id: ID!
            status: String
        }
        ';

        $task = factory(Task::class)->create();
        assert($task instanceof Task);

        $post = factory(Post::class)->make();
        assert($post instanceof Post);
        $post->task()->associate($task);
        $post->save();

        $postStatus = factory(PostStatus::class)->make();
        assert($postStatus instanceof PostStatus);
        $postStatus->post()->associate($post);
        $postStatus->save();

        $this->graphQL(/** @lang GraphQL */ '
        {
            tasks {
                id
                postStatus {
                    id
                    status
                }
            }
        }
        ')->assertExactJson([
            "data" => [
                "tasks" => [
                    [
                        "id" => (string) $task->id,
                        "postStatus" => [
                            "id" => (string) $postStatus->id,
                            "status" => $postStatus->status
                        ],
                    ]
                ]
            ]
        ]);
    }
}
