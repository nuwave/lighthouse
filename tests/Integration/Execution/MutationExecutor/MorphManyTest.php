<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Image;
use Tests\Utils\Models\Task;

class MorphManyTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Task {
        id: ID!
        name: String!
        images: [Image!]!
    }

    type Image {
        id: ID!
        url: String
    }

    type Mutation {
        createTask(input: CreateTaskInput! @spread): Task @create
        updateTask(input: UpdateTaskInput! @spread): Task @update
        upsertTask(input: UpdateTaskInput! @spread): Task @upsert
    }

    input CreateTaskInput {
        name: String!
        images: CreateImageRelation
    }

    input CreateImageRelation {
        create: [CreateImageInput!]
        upsert: [UpsertImageInput!]
    }

    input CreateImageInput {
        url: String
    }

    input UpdateTaskInput {
        id: ID
        name: String
        images: UpdateImageRelation
    }

    input UpdateImageRelation {
        create: [CreateImageInput!]
        update: [UpdateImageInput!]
        upsert: [UpsertImageInput!]
        delete: [ID!]
    }

    input UpdateImageInput {
        id: ID!
        url: String
    }

    input UpsertTaskInput {
        id: ID!
        name: String
        images: UpsertImageRelation
    }

    input UpsertImageRelation {
        create: [CreateImageInput!]
        update: [UpdateImageInput!]
        upsert: [UpsertImageInput!]
        delete: [ID!]
    }

    input UpsertImageInput {
        id: ID
        url: String
    }
    '.self::PLACEHOLDER_QUERY;

    public function testCanCreateWithNewMorphMany(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createTask(input: {
                name: "foo"
                images: {
                    create: [{
                        url: "foo"
                    }]
                }
            }) {
                id
                name
                images {
                    url
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'images' => [
                        [
                            'url' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanCreateWithUpsertMorphMany(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createTask(input: {
                name: "foo"
                images: {
                    upsert: [{
                        id: 1
                        url: "foo"
                    }]
                }
            }) {
                id
                name
                images {
                    url
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'images' => [
                        [
                            'url' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertMorphManyWithoutId(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertTask(input: {
                name: "foo"
                images: {
                    upsert: [{
                        url: "foo"
                    }]
                }
            }) {
                id
                name
                images {
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
                    'images' => [
                        [
                            'id' => 1,
                            'url' => 'foo',
                        ],
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
                images: {
                    create: null
                    update: null
                    upsert: null
                    delete: null
                }
            }) {
                name
                images {
                    url
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateTask' => [
                    'name' => 'foo',
                    'images' => [],
                ],
            ],
        ]);
    }

    public function existingModelMutations()
    {
        return [
            ['Update action' => 'update'],
            ['Upsert action' => 'upsert'],
        ];
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateWithNewMorphMany(string $action): void
    {
        factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ "
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
                images: {
                    create: [{
                        url: \"foo\"
                    }]
                }
            }) {
                id
                name
                images {
                    url
                }
            }
        }
        ")->assertJson([
            'data' => [
                "${action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'images' => [
                        [
                            'url' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateAndUpdateMorphMany(string $action): void
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
                images: {
                    update: [{
                        id: 1
                        url: \"foo\"
                    }]
                }
            }) {
                id
                name
                images {
                    url
                }
            }
        }
        ")->assertJson([
            'data' => [
                "${action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'images' => [
                        [
                            'url' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateAndUpsertMorphMany(string $action): void
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
                images: {
                    upsert: [{
                        id: 1
                        url: \"foo\"
                    }]
                }
            }) {
                id
                name
                images {
                    url
                }
            }
        }
        ")->assertJson([
            'data' => [
                "${action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'images' => [
                        [
                            'url' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateAndDeleteMorphMany(string $action): void
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
                images: {
                    delete: [1]
                }
            }) {
                id
                name
                images {
                    url
                }
            }
        }
        ")->assertJson([
            'data' => [
                "${action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'images' => [],
                ],
            ],
        ]);
    }
}
