<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Image;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class MorphToDirectiveTest extends DBTestCase
{
    /**
     * Auth user.
     *
     * @var \Tests\Utils\Models\User
     */
    protected $user;

    /**
     * User's task.
     *
     * @var \Tests\Utils\Models\Task
     */
    protected $task;

    /**
     * Task's image.
     *
     * @var \Tests\Utils\Models\Image
     */
    protected $image;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->task = factory(Task::class)->create([
            'user_id' => $this->user->id,
        ]);
        $this->image = $this->task->images()->save(factory(Image::class)->create());
    }

    public function testCanResolveMorphToRelationship(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Image {
            id: ID!
            from: String
            to: String
            imageable: Task! @morphTo(relation: "imageable")
        }

        type Task {
            id: ID!
            name: String!
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            image(id: $id) {
                id
                from
                to
                imageable {
                    id
                    name
                }
            }
        }
        ', [
            'id' => $this->image->id,
        ])->assertJson([
            'data' => [
                'image' => [
                    'id' => $this->image->id,
                    'from' => $this->image->from,
                    'to' => $this->image->to,
                    'imageable' => [
                        'id' => $this->task->id,
                        'name' => $this->task->name,
                    ],
                ],
            ],
        ]);
    }

    public function testCanResolveMorphToWithCustomName(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Image {
            id: ID!
            from: String
            to: String
            customImageable: Task! @morphTo(relation: "imageable")
        }

        type Task {
            id: ID!
            name: String!
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            image(id: $id) {
                id
                from
                to
                customImageable {
                    id
                    name
                }
            }
        }
        ', [
            'id' => $this->image->id,
        ])->assertJson([
            'data' => [
                'image' => [
                    'id' => $this->image->id,
                    'from' => $this->image->from,
                    'to' => $this->image->to,
                    'customImageable' => [
                        'id' => $this->task->id,
                        'name' => $this->task->name,
                    ],
                ],
            ],
        ]);
    }

    public function testCanResolveMorphToUsingInterfaces(): void
    {
        $post = factory(Post::class)->create([
            'user_id' => $this->user->id,
        ]);
        $postImage = $post->images()->save(
            factory(Image::class)->create()
        );

        $this->schema = /** @lang GraphQL */ '
        interface Imageable {
            id: ID!
        }

        type Task implements Imageable {
            id: ID!
            name: String!
        }

        type Post implements Imageable {
            id: ID!
            title: String!
        }

        type Image {
            id: ID!
            from: String
            to: String
            imageable: Imageable! @morphTo
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($taskImage: ID!, $postImage: ID!){
            taskImage: image(id: $taskImage) {
                id
                from
                to
                imageable {
                    ... on Task {
                        id
                        name
                    }
                    ... on Post {
                        id
                        title
                    }
                }
            }
            postImage: image(id: $postImage) {
                id
                from
                to
                imageable {
                    ... on Task {
                        id
                        name
                    }
                    ... on Post {
                        id
                        title
                    }
                }
            }
        }
        ', [
            'taskImage' => $this->image->id,
            'postImage' => $postImage->id,
        ])->assertJson([
            'data' => [
                'taskImage' => [
                    'id' => $this->image->id,
                    'from' => $this->image->from,
                    'to' => $this->image->to,
                    'imageable' => [
                        'id' => $this->task->id,
                        'name' => $this->task->name,
                    ],
                ],
                'postImage' => [
                    'id' => $postImage->id,
                    'from' => $postImage->from,
                    'to' => $postImage->to,
                    'imageable' => [
                        'id' => $post->id,
                        'title' => $post->title,
                    ],
                ],
            ],
        ]);
    }
}
