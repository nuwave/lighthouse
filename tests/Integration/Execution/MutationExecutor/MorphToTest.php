<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Image;
use Tests\Utils\Models\Task;

class MorphToTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Task {
        id: ID
        name: String
    }

    type Image {
        id: ID
        url: String
        imageable: Task
    }

    type Mutation {
        createImage(input: CreateImageInput! @spread): Image @create
        updateImage(input: UpdateImageInput! @spread): Image @update
        upsertImage(input: UpsertImageInput! @spread): Image @upsert
    }

    input CreateImageInput {
        from: String
        to: String
        url: String
        imageable: CreateImageableOperations
    }

    input CreateImageableOperations {
        connect: ConnectImageableInput
    }

    input ConnectImageableInput {
        type: String!
        id: ID!
    }

    input UpdateImageInput {
        id: ID!
        from: String
        to: String
        url: String
        imageable: UpdateImageableOperations
    }

    input UpdateImageableOperations {
        connect: ConnectImageableInput
        disconnect: Boolean
        delete: Boolean
    }

    input UpsertImageInput {
        id: ID!
        from: String
        to: String
        url: String
        imageable: UpsertImageableOperations
    }

    input UpsertImageableOperations {
        connect: ConnectImageableInput
        disconnect: Boolean
        delete: Boolean
    }
    '.self::PLACEHOLDER_QUERY;

    public function testConnectsMorphTo(): void
    {
        factory(Task::class)->create(['name' => 'first_task']);

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createImage(input: {
                url: "foo"
                imageable: {
                    connect: {
                        type: "Tests\\\Utils\\\Models\\\Task"
                        id: 1
                    }
                }
            }) {
                id
                url
                imageable {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createImage' => [
                    'id' => '1',
                    'url' => 'foo',
                    'imageable' => [
                        'id' => '1',
                        'name' => 'first_task',
                    ],
                ],
            ],
        ]);
    }

    public function testConnectsMorphToWithUpsert(): void
    {
        factory(Task::class)->create(['name' => 'first_task']);

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertImage(input: {
                id: 1
                url: "foo"
                imageable: {
                    connect: {
                        type: "Tests\\\Utils\\\Models\\\Task"
                        id: 1
                    }
                }
            }) {
                id
                url
                imageable {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertImage' => [
                    'id' => '1',
                    'url' => 'foo',
                    'imageable' => [
                        'id' => '1',
                        'name' => 'first_task',
                    ],
                ],
            ],
        ]);
    }

    public function testAllowsNullOperations(): void
    {
        factory(Image::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateImage(input: {
                id: 1
                url: "foo"
                imageable: {
                    connect: null
                    disconnect: null
                    delete: null
                }
            }) {
                url
                imageable {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateImage' => [
                    'url' => 'foo',
                    'imageable' => null,
                ],
            ],
        ]);
    }

    public function existingModelMutations(): array
    {
        return [
            ['Update action' => 'update'],
            ['Upsert action' => 'upsert'],
        ];
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testDisconnectsMorphTo(string $action): void
    {
        /** @var \Tests\Utils\Models\Task $task */
        $task = factory(Task::class)->create(['name' => 'first_task']);
        $task->image()->create([
            'url' => 'bar',
        ]);

        $this->graphQL("
        mutation {
            ${action}Image(input: {
                id: 1
                url: \"foo\"
                imageable: {
                    disconnect: true
                }
            }) {
                url
                imageable {
                    id
                    name
                }
            }
        }
        ")->assertJson([
            'data' => [
                "${action}Image" => [
                    'url' => 'foo',
                    'imageable' => null,
                ],
            ],
        ]);
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testDeletesMorphTo(string $action): void
    {
        /** @var \Tests\Utils\Models\Task $task */
        $task = factory(Task::class)->create(['name' => 'first_task']);
        $task->image()->create([
            'url' => 'bar',
        ]);

        $this->graphQL(/** @lang GraphQL */ "
        mutation {
            ${action}Image(input: {
                id: 1
                url: \"foo\"
                imageable: {
                    delete: true
                }
            }) {
                url
                imageable {
                    id
                    name
                }
            }
        }
        ")->assertJson([
            'data' => [
                "${action}Image" => [
                    'url' => 'foo',
                    'imageable' => null,
                ],
            ],
        ]);

        $this->assertSame(
            0,
            Task::count()
        );
    }
}
