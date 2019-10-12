<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;

class HasOneTest extends DBTestCase
{
    protected $schema = '
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
        post: UpdatePostRelation
    }
    
    input UpdatePostRelation {
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
        id: ID!
        name: String
        post: UpsertPostRelation
    }

    input UpsertPostRelation {
        create: CreatePostInput
        update: UpdatePostInput
        upsert: UpsertPostInput
        delete: ID
    }

    input UpsertPostInput {
        id: ID!
        title: String
    }
    '.self::PLACEHOLDER_QUERY;

    public function testCanCreateWithNewHasOne(): void
    {
        $this->graphQL('
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
        $this->graphQL('
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
        $this->graphQL('
        mutation {
            upsertTask(input: {
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

    public function actionsOverExistingDataProvider()
    {
        yield ['Update action' => 'update'];
        yield ['Upsert action' => 'upsert'];
    }

    /**
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testCanUpdateWithNewHasOne($action): void
    {
        factory(Task::class)->create();

        $this->graphQL("
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
                post: {
                    create: {
                        title: \"bar\"
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
        ")->assertJson([
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
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testCanUpdateAndUpdateHasOne($action): void
    {
        factory(Task::class)
            ->create()
            ->post()
            ->save(
                factory(Post::class)->create()
            );

        $this->graphQL("
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
                post: {
                    update: {
                        id: 1
                        title: \"bar\"
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
        ")->assertJson([
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
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testCanUpdateAndUpsertHasOne($action): void
    {
        factory(Task::class)
            ->create()
            ->post()
            ->save(
                factory(Post::class)->create()
            );

        $this->graphQL("
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
                post: {
                    upsert: {
                        id: 1
                        title: \"bar\"
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
        ")->assertJson([
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
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testCanUpdateAndDeleteHasOne($action): void
    {
        factory(Task::class)
            ->create()
            ->post()
            ->save(
                factory(Post::class)->create()
            );

        $this->graphQL("
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
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
        ")->assertJson([
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
