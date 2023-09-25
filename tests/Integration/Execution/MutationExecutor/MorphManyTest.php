<?php declare(strict_types=1);

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Image;
use Tests\Utils\Models\Task;

final class MorphManyTest extends DBTestCase
{
    protected string $schema = /** @lang GraphQL */ '
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
        upsertTask(input: UpsertTaskInput! @spread): Task @upsert
    }

    input CreateTaskInput {
        name: String!
        images: CreateImageRelation
    }

    input CreateImageRelation {
        create: [CreateImageInput!]
        upsert: [UpsertImageInput!]
        connect: [ID!]
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
        connect: [ID!]
        disconnect: [ID!]
    }

    input UpdateImageInput {
        id: ID!
        url: String
    }

    input UpsertTaskInput {
        id: ID
        name: String
        images: UpsertImageRelation
    }

    input UpsertImageRelation {
        create: [CreateImageInput!]
        update: [UpdateImageInput!]
        upsert: [UpsertImageInput!]
        delete: [ID!]
        connect: [ID!]
        disconnect: [ID!]
    }

    input UpsertImageInput {
        id: ID
        url: String
    }
    ' . self::PLACEHOLDER_QUERY;

    public function testCreateWithNewMorphMany(): void
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

    public function testCreateWithUpsertMorphMany(): void
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

    public function testCreateWithConnectMorphMany(): void
    {
        $image1 = factory(Image::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
            mutation ($input: CreateTaskInput!){
                createTask(input: $input) {
                    id
                    name
                    images {
                        url
                    }
                }
            }
        ', [
            'input' => [
                'name' => 'foo',
                'images' => [
                    'connect' => [
                        $image1->id,
                    ],
                ],
            ],
        ])->assertJson([
            'data' => [
                'createTask' => [
                    'name' => 'foo',
                    'images' => [
                        [
                            'url' => $image1->url,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertMorphManyWithoutId(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
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
        ')->assertJson([
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

    /** @return array<array<string, string>> */
    public static function existingModelMutations(): array
    {
        return [
            ['Update action' => 'update'],
            ['Upsert action' => 'upsert'],
        ];
    }

    /** @dataProvider existingModelMutations */
    public function testUpdateWithNewMorphMany(string $action): void
    {
        factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Task(input: {
                id: 1
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
GRAPHQL
        )->assertJson([
            'data' => [
                "{$action}Task" => [
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

    /** @dataProvider existingModelMutations */
    public function testUpdateAndUpdateMorphMany(string $action): void
    {
        factory(Task::class)
            ->create()
            ->images()
            ->save(
                factory(Image::class)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Task(input: {
                id: 1
                name: "foo"
                images: {
                    update: [{
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
GRAPHQL
        )->assertJson([
            'data' => [
                "{$action}Task" => [
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

    /** @dataProvider existingModelMutations */
    public function testUpdateAndUpsertMorphMany(string $action): void
    {
        factory(Task::class)
            ->create()
            ->images()
            ->save(
                factory(Image::class)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Task(input: {
                id: 1
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
GRAPHQL
        )->assertJson([
            'data' => [
                "{$action}Task" => [
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

    /** @dataProvider existingModelMutations */
    public function testUpdateAndDeleteMorphMany(string $action): void
    {
        factory(Task::class)
            ->create()
            ->images()
            ->save(
                factory(Image::class)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Task(input: {
                id: 1
                name: "foo"
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
GRAPHQL
        )->assertJson([
            'data' => [
                "{$action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'images' => [],
                ],
            ],
        ]);
    }

    /** @dataProvider existingModelMutations */
    public function testUpdateAndConnectMorphMany(string $action): void
    {
        $task = factory(Task::class)->create();
        $image = factory(Image::class)->create();

        $upperAction = ucfirst($action);
        $actionInput = "{$upperAction}TaskInput";

        $newTaskName = 'foo';

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
            mutation (\$input: {$actionInput}!) {
                {$action}Task(input: \$input) {
                    id
                    name
                    images {
                        url
                    }
                }
            }
GRAPHQL
            , [
                'input' => [
                    'id' => $task->id,
                    'name' => $newTaskName,
                    'images' => [
                        'connect' => [
                            $image->id,
                        ],
                    ],
                ],
            ])->assertJson([
                'data' => [
                    "{$action}Task" => [
                        'id' => "{$task->id}",
                        'name' => $newTaskName,
                        'images' => [
                            [
                                'url' => $image->url,
                            ],
                        ],
                    ],
                ],
            ]);
    }

    /** @dataProvider existingModelMutations */
    public function testUpdateAndDisconnectMorphMany(string $action): void
    {
        /** @var \Tests\Utils\Models\Task $task */
        $task = factory(Task::class)->create();

        /** @var \Tests\Utils\Models\Image $image */
        $image = factory(Image::class)->make();
        $task->images()->save($image);

        $upperAction = ucfirst($action);
        $actionInput = "{$upperAction}TaskInput";

        $newTaskName = 'foo';

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
            mutation (\$input: {$actionInput}!) {
                {$action}Task(input: \$input) {
                    id
                    name
                    images {
                        url
                    }
                }
            }
GRAPHQL
            , [
                'input' => [
                    'id' => $task->id,
                    'name' => $newTaskName,
                    'images' => [
                        'disconnect' => [
                            $image->id,
                        ],
                    ],
                ],
            ])->assertJson([
                'data' => [
                    "{$action}Task" => [
                        'id' => "{$task->id}",
                        'name' => $newTaskName,
                        'images' => [],
                    ],
                ],
            ]);

        $this->assertNull($image->refresh()->imageable);
    }

    /** @dataProvider existingModelMutations */
    public function testDisconnectModelEventsMorphMany(string $action): void
    {
        /** @var \Tests\Utils\Models\Task $task */
        $task = factory(Task::class)->create();

        /** @var \Tests\Utils\Models\Image $image */
        $image = factory(Image::class)->make();
        $task->images()->save($image);

        $upperAction = ucfirst($action);
        $actionInput = "{$upperAction}TaskInput";

        $calledImageSaving = 0;
        Image::saving(static function () use (&$calledImageSaving): void {
            ++$calledImageSaving;
        });

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
            mutation (\$input: {$actionInput}!) {
                {$action}Task(input: \$input) {
                    id
                    images {
                        url
                    }
                }
            }
GRAPHQL
            , [
                'input' => [
                    'id' => $task->id,
                    'images' => [
                        'disconnect' => [
                            $image->id,
                        ],
                    ],
                ],
            ])->assertJson([
                'data' => [
                    "{$action}Task" => [
                        'id' => "{$task->id}",
                        'images' => [],
                    ],
                ],
            ]);

        $this->assertSame(1, $calledImageSaving);
    }
}
