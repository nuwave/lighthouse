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
    }
    
    input CreateTaskInput {
        name: String!
        post: CreatePostRelation
    }
    
    input CreatePostRelation {
        create: CreatePostInput!
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
        delete: ID
    }
    
    input UpdatePostInput {
        id: ID!
        title: String
    }
    ';

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema .= $this->placeholderQuery();
    }

    /**
     * @test
     */
    public function itCanCreateWithNewHasOne(): void
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

    /**
     * @test
     */
    public function itCanUpdateWithNewHasOne(): void
    {
        factory(Task::class)->create();

        $this->graphQL('
        mutation {
            updateTask(input: {
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
        ')->assertJson([
            'data' => [
                'updateTask' => [
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
     * @test
     */
    public function itCanUpdateAndUpdateHasOne(): void
    {
        factory(Task::class)
            ->create()
            ->post()
            ->save(
                factory(Post::class)->create()
            );

        $this->graphQL('
        mutation {
            updateTask(input: {
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
        ')->assertJson([
            'data' => [
                'updateTask' => [
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
     * @test
     */
    public function itCanUpdateAndDeleteHasOne(): void
    {
        factory(Task::class)
            ->create()
            ->post()
            ->save(
                factory(Post::class)->create()
            );

        $this->graphQL('
        mutation {
            updateTask(input: {
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
        ')->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'post' => null,
                ],
            ],
        ]);
    }
}
