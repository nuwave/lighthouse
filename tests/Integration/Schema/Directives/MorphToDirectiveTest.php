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

    public function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->task = factory(Task::class)->create([
            'user_id' => $this->user->id,
        ]);
        $this->image = $this->task->images()
            ->save(
                factory(Image::class)->create()
            );
    }

    public function testCanResolveMorphToRelationship(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Image {
            id: ID!
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
        /** @var \Tests\Utils\Models\Post $post */
        $post = factory(Post::class)->create([
            'user_id' => $this->user->id,
        ]);
        /** @var \Tests\Utils\Models\Image $postImage */
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
                    'imageable' => [
                        'id' => $this->task->id,
                        'name' => $this->task->name,
                    ],
                ],
                'postImage' => [
                    'id' => $postImage->id,
                    'imageable' => [
                        'id' => $post->id,
                        'title' => $post->title,
                    ],
                ],
            ],
        ]);
    }
}
