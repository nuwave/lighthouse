<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;

class HasOneTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
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
        upsertTask(input: UpsertTaskInput! @spread): Task @upsert
    }

    input CreateTaskInput {
        name: String!
        post: CreatePostRelation
    }

    input CreatePostRelation {
        create: CreatePostInput
        upsert: UpsertPostInput
    }

    input CreatePostInput {
        title: String!
    }

    input UpdateTaskInput {
        id: ID!
        name: String
        post: UpdatePostHasOne
    }

    input UpdatePostHasOne {
        create: CreatePostInput
        update: UpdatePostInput
        upsert: UpsertPostInput
        delete: ID
    }

    input UpdatePostInput {
        id: ID!
        title: String
    }

    input UpsertTaskInput {
        id: ID
        name: String
        post: UpsertPostHasOne
    }

    input UpsertPostHasOne {
        create: CreatePostInput
        update: UpdatePostInput
        upsert: UpsertPostInput
        delete: ID
    }

    input UpsertPostInput {
        id: ID
        title: String
    }
    '.self::PLACEHOLDER_QUERY;

    public function testCanCreateWithNewHasOne(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
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
        ')->assertJson([
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

    public function testCanUpsertWithNewHasOne(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createTask(input: {
                name: "foo"
                post: {
                    upsert: {
                        id: 1
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
        ')->assertJson([
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

    public function testCanCreateUsingUpsertWithNewHasOne(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertTask(input: {
                id: 2
                name: "foo"
                post: {
                    upsert: {
                        id: 3
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
        ')->assertJson([
            'data' => [
                'upsertTask' => [
                    'id' => '2',
                    'name' => 'foo',
                    'post' => [
                        'id' => '3',
                        'title' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertHasOneWithoutId(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertTask(input: {
                name: "foo"
                post: {
                    upsert: {
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
        ')->assertJson([
            'data' => [
                'upsertTask' => [
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

    public function testAllowsNullOperations(): void
    {
        factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                post: {
                    create: null
                    update: null
                    upsert: null
                    delete: null
                }
            }) {
                name
                post {
                    id
                }
            }
        }
GRAPHQL
        )->assertJson([
            'data' => [
                'updateTask' => [
                    'name' => 'foo',
                    'post' => null,
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
    public function testCanUpdateWithNewHasOne(string $action): void
    {
        factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}Task(input: {
                id: 1
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
GRAPHQL
            )->assertJson([
                'data' => [
                    "${action}Task" => [
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

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateAndUpdateHasOne(string $action): void
    {
        factory(Task::class)
            ->create()
            ->post()
            ->save(
                factory(Post::class)->create()
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}Task(input: {
                id: 1
                name: "foo"
                post: {
                    update: {
                        id: 1
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
GRAPHQL
        )->assertJson([
            'data' => [
                "${action}Task" => [
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

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateAndUpsertHasOne(string $action): void
    {
        factory(Task::class)
            ->create()
            ->post()
            ->save(
                factory(Post::class)->create()
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}Task(input: {
                id: 1
                name: "foo"
                post: {
                    upsert: {
                        id: 1
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
GRAPHQL
        )->assertJson([
            'data' => [
                "${action}Task" => [
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

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateAndDeleteHasOne(string $action): void
    {
        factory(Task::class)
            ->create()
            ->post()
            ->save(
                factory(Post::class)->create()
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}Task(input: {
                id: 1
                name: "foo"
                post: {
                    delete: 1
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

GRAPHQL
        )->assertJson([
            'data' => [
                "${action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'post' => null,
                ],
            ],
        ]);
    }
}
