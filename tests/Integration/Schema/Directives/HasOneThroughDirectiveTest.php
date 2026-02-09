<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\PostStatus;

final class HasOneThroughDirectiveTest extends DBTestCase
{
    public function testQueryHasOneThroughRelationship(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $post = factory(Post::class)->create();
        $this->assertInstanceOf(Post::class, $post);

        $postStatus = factory(PostStatus::class)->make();
        $this->assertInstanceOf(PostStatus::class, $postStatus);
        $postStatus->post()->associate($post);
        $postStatus->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            tasks {
                id
                postStatus {
                    id
                    status
                }
            }
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'tasks' => [
                    [
                        'id' => (string) $post->task->id,
                        'postStatus' => [
                            'id' => (string) $postStatus->id,
                            'status' => $postStatus->status,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
