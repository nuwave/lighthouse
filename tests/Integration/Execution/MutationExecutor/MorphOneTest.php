<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Image;
use Tests\Utils\Models\Task;

class MorphOneTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Task {
        id: ID!
        name: String!
        image: Image
    }

    type Image {
        id: ID!
        url: String
    }

    type Mutation {
        createTask(input: CreateTaskInput! @spread): Task @create
        updateTask(input: UpdateTaskInput! @spread): Task @update
        upsertTask(input: UpsertTaskInput! @spread): Task @upsert
    }

    input CreateTaskInput {
        name: String!
        image: CreateImageRelation
    }

    input CreateImageRelation {
        create: CreateImageInput
        upsert: UpsertImageInput
    }

    input CreateImageInput {
        url: String
    }

    input UpdateTaskInput {
        id: ID!
        name: String
        image: UpdateImageRelation
    }

    input UpdateImageRelation {
        create: CreateImageInput
        update: UpdateImageInput
        upsert: UpsertImageInput
        delete: ID
    }

    input UpdateImageInput {
        id: ID!
        url: String
    }

    input UpsertTaskInput {
        id: ID
        name: String
        image: UpsertImageRelation
    }

    input UpsertImageRelation {
        create: CreateImageInput
        update: UpdateImageInput
        upsert: UpsertImageInput
        delete: ID
    }

    input UpsertImageInput {
        id: ID
        url: String
    }
    '.self::PLACEHOLDER_QUERY;

    public function testCanCreateWithNewMorphOne(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createTask(input: {
                name: "foo"
                image: {
                    create: {
                        url: "foo"
                    }
                }
            }) {
                id
                name
                image {
                    url
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'image' => [
                        'url' => 'foo',
                    ],
                ],
            ],
        ]);
    }

    public function testCanCreateWithUpsertMorphOne(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createTask(input: {
                name: "foo"
                image: {
                    upsert: {
                        id: 1
                        url: "foo"
                    }
                }
            }) {
                id
                name
                image {
                    url
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'image' => [
                        'url' => 'foo',
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertMorphOneWithoutId(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertTask(input: {
                name: "foo"
                image: {
                    upsert: {
                        url: "foo"
                    }
                }
            }) {
                id
                name
                image {
                    id
                    url
                }
            }
        }
GRAPHQL
        )->assertJson([
            'data' => [
                'upsertTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'image' => [
                        'id' => 1,
                        'url' => 'foo',
                    ],
                ],
            ],
        ]);
    }

    public function testAllowsNullOperations(): void
    {
        factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                image: {
                    create: null
                    update: null
                    upsert: null
                    delete: null
                }
            }) {
                name
                image {
                    url
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateTask' => [
                    'name' => 'foo',
                    'image' => null,
                ],
            ],
        ]);
    }

    /**
     * @return array<array<string, string>>
     */
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
    public function testCanUpdateWithNewMorphOne(string $action): void
    {
        factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ "
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
                image: {
                    create: {
                        url: \"foo\"
                    }
                }
            }) {
                id
                name
                image {
                    url
                }
            }
        }
        ")->assertJson([
            'data' => [
                "${action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'image' => [
                        'url' => 'foo',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateWithUpsertMorphOne(string $action): void
    {
        factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ "
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
                image: {
                    upsert: {
                        id: 1
                        url: \"foo\"
                    }
                }
            }) {
                id
                name
                image {
                    url
                }
            }
        }
        ")->assertJson([
            'data' => [
                "${action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'image' => [
                        'url' => 'foo',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateAndUpdateMorphOne(string $action): void
    {
        factory(Task::class)
            ->create()
            ->images()
            ->save(
                factory(Image::class)->create()
            );

        $this->graphQL(/** @lang GraphQL */ "
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
                image: {
                    update: {
                        id: 1
                        url: \"foo\"
                    }
                }
            }) {
                id
                name
                image {
                    url
                }
            }
        }
        ")->assertJson([
            'data' => [
                "${action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'image' => [
                        'url' => 'foo',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateAndDeleteMorphOne(string $action): void
    {
        factory(Task::class)
            ->create()
            ->images()
            ->save(
                factory(Image::class)->create()
            );

        $this->graphQL("
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
                image: {
                    delete: 1
                }
            }) {
                id
                name
                image {
                    url
                }
            }
        }
        ")->assertJson([
            'data' => [
                "${action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'image' => null,
                ],
            ],
        ]);
    }
}
