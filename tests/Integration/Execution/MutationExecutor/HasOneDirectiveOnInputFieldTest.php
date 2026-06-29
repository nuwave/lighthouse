<?php declare(strict_types=1);

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;

final class HasOneDirectiveOnInputFieldTest extends DBTestCase
{
    protected string $schema = /** @lang GraphQL */ <<<'GRAPHQL'
    type Task {
        id: ID!
        name: String!
        post: Post @hasOne
    }

    type Post {
        id: ID!
        title: String!
        body: String!
    }

    type Mutation {
        createTask(input: CreateTaskInput! @spread): Task @create
        updateTask(input: UpdateTaskInput! @spread): Task @update
    }

    input CreateTaskInput {
        name: String!
        post: CreatePostRelation @hasOne
    }

    input CreatePostRelation {
        create: CreatePostInput
    }

    input CreatePostInput {
        title: String!
    }

    input UpdateTaskInput {
        id: ID!
        name: String
        post: UpdatePostRelation @hasOne
    }

    input UpdatePostRelation {
        create: CreatePostInput
        update: UpdatePostInput
        upsert: UpsertPostInput
        delete: ID
    }

    input UpdatePostInput {
        id: ID!
        title: String
    }

    input UpsertPostInput {
        id: ID
        title: String!
    }
    GRAPHQL . self::PLACEHOLDER_QUERY;

    public function testCreateWithNewHasOne(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createTask(input: {
                name: "foo"
                post: {
                    create: {
                        title: "bar"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'post' => [
                        'id' => '1',
                        'title' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    public function testUpdateWithCreateHasOne(): void
    {
        $task = factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateTask(input: {
                id: 1
                name: "updated"
                post: {
                    create: {
                        title: "new post"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'updated',
                    'post' => [
                        'id' => '1',
                        'title' => 'new post',
                    ],
                ],
            ],
        ]);
    }

    public function testUpdateWithUpdateHasOne(): void
    {
        $task = factory(Task::class)->create();
        $post = factory(Post::class)->make();
        $post->title = 'original';
        $post->task()->associate($task);
        $post->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateTask(input: {
                id: 1
                post: {
                    update: {
                        id: 1
                        title: "changed"
                    }
                }
            }) {
                id
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'post' => [
                        'id' => '1',
                        'title' => 'changed',
                    ],
                ],
            ],
        ]);
    }

    public function testUpdateWithDeleteHasOne(): void
    {
        $task = factory(Task::class)->create();
        $post = factory(Post::class)->make();
        $post->task()->associate($task);
        $post->save();

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            updateTask(input: {
                id: 1
                post: {
                    delete: {$post->id}
                }
            }) {
                id
                post {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'post' => null,
                ],
            ],
        ]);

        $this->assertNull(Post::find($post->id));
    }
}
