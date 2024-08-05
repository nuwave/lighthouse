<?php declare(strict_types=1);

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Image;
use Tests\Utils\Models\Task;

final class MorphOneTest extends DBTestCase
{
    protected string $schema = /** @lang GraphQL */ '
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
    ' . self::PLACEHOLDER_QUERY;

    public function testCreateWithNewMorphOne(): void
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

    public function testCreateWithUpsertMorphOne(): void
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

    /** @return iterable<array{string}> */
    public static function existingModelMutations(): iterable
    {
        yield 'Update action' => ['update'];
        yield 'Upsert action' => ['upsert'];
    }

    /** @dataProvider existingModelMutations */
    public function testUpdateWithNewMorphOne(string $action): void
    {
        factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Task(input: {
                id: 1
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
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'image' => [
                        'url' => 'foo',
                    ],
                ],
            ],
        ]);
    }

    /** @dataProvider existingModelMutations */
    public function testUpdateWithUpsertMorphOne(string $action): void
    {
        factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Task(input: {
                id: 1
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
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'image' => [
                        'url' => 'foo',
                    ],
                ],
            ],
        ]);
    }

    /** @dataProvider existingModelMutations */
    public function testUpdateAndUpdateMorphOne(string $action): void
    {
        $task = factory(Task::class)->create();
        assert($task instanceof Task);

        $task->images()
            ->save(
                factory(Image::class)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Task(input: {
                id: 1
                name: "foo"
                image: {
                    update: {
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
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'image' => [
                        'url' => 'foo',
                    ],
                ],
            ],
        ]);
    }

    /** @dataProvider existingModelMutations */
    public function testUpdateAndDeleteMorphOne(string $action): void
    {
        $task = factory(Task::class)->create();
        assert($task instanceof Task);

        $task->images()
            ->save(
                factory(Image::class)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Task(input: {
                id: 1
                name: "foo"
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
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'image' => null,
                ],
            ],
        ]);
    }

    public function testNestedConnectMorphOne(): void
    {
        $task = factory(Task::class)->create();
        assert($task instanceof Task);

        $image = factory(Image::class)->create();
        assert($image instanceof Image);

        $this->graphQL(/** @lang GraphQL */ '
        mutation ($input: UpdateTaskInput!) {
            updateTask(input: $input) {
                id
                name
                image {
                    url
                }
            }
        }
        ', [
            'input' => [
                'id' => $task->id,
                'name' => 'foo',
                'image' => [
                    'upsert' => [
                        'id' => $image->id,
                        'url' => 'foo',
                    ],
                ],
            ],
        ])->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'image' => [
                        'url' => 'foo',
                    ],
                ],
            ],
        ]);
    }
}
