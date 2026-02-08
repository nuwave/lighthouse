<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Image;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class MorphOneDirectiveTest extends DBTestCase
{
    /**
     * The authenticated user.
     *
     * @var User
     */
    protected $user;

    /**
     * User's task.
     *
     * @var Task
     */
    protected $task;

    /**
     * Task's image.
     *
     * @var Image
     */
    protected $image;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $this->user);

        $this->task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $this->task);
        $this->task->user()->associate($this->user);
        $this->task->save();

        $this->image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $this->image);
        $this->image->imageable()->associate($this->task);
        $this->image->save();
    }

    public function testResolveMorphOneRelationship(): void
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
        query ($id: ID!) {
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

    public function testResolveMorphOneWithCustomName(): void
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
        query ($id: ID!) {
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
