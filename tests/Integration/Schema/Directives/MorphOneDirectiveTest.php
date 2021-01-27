<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Image;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class MorphOneDirectiveTest extends DBTestCase
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
        $this->image = $this->task
            ->images()
            ->save(
                factory(Image::class)->create()
            );
    }

    public function testCanResolveMorphOneRelationship(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Image {
            id: ID!
        }

        type Task {
            id: ID!
            name: String!
            image: Image! @morphOne
        }

        type Query {
            task (
                id: ID! @eq
            ): Task @find
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!){
            task(id: $id) {
                id
                name
                image {
                    id
                }
            }
        }
        ', [
            'id' => $this->task->id,
        ])->assertJson([
            'data' => [
                'task' => [
                    'id' => $this->task->id,
                    'name' => $this->task->name,
                    'image' => [
                        'id' => $this->image->id,
                    ],
                ],
            ],
        ]);
    }

    public function testCanResolveMorphOneWithCustomName(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Image {
            id: ID!
        }

        type Task {
            id: ID!
            name: String!
            customImage: Image! @morphOne(relation: "image")
        }

        type Query {
            task (
                id: ID! @eq
            ): Task @find
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!){
            task(id: $id) {
                id
                name
                customImage {
                    id
                }
            }
        }
        ', [
            'id' => $this->task->id,
        ])->assertJson([
            'data' => [
                'task' => [
                    'id' => $this->task->id,
                    'name' => $this->task->name,
                    'customImage' => [
                        'id' => $this->image->id,
                    ],
                ],
            ],
        ]);
    }
}
