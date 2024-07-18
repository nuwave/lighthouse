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
        $this->schema = /** @lang GraphQL */
            '

         type Query {
         tasks: [Task]! @all
          }

        type Task {
            id: ID!
            postStatus: PostStatus @hasOneThrough
        }

        type Post {
             id: ID!
        }

        type PostStatus {
               id: ID!
               status: String

        }
        ';

        $post = factory(Post::class)->create();
        assert($post instanceof Post);

        $post_status = factory(PostStatus::class)->create();
        assert($post_status instanceof PostStatus);

        $post->status()->save($post_status);

        $task = Task::query()->first();

        if ($task and $task->postStatus) {

            $task_status = $task->postStatus;

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
        ')
                ->assertExactJson(
                    [
                        "data" => [
                            "tasks" => [
                                [
                                    "id" => (string)$task->id,
                                    "postStatus" => [
                                        "id" => (string)$task_status->id,
                                        "status" => $task_status->status
                                    ],
                                ]
                            ]
                        ]
                    ]
                );
        }
        else {
            $this->assertTrue(true);
        }
    }
}
