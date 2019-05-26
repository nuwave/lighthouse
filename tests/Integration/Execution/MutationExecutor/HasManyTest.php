<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class HasManyTest extends DBTestCase
{
    protected $schema = '
    type Task {
        id: ID!
        name: String!
    }
    
    type User {
        id: ID!
        name: String
        tasks: [Task!]! @hasMany
    }
    
    type Mutation {
        createUser(input: CreateUserInput! @spread): User @create
        updateUser(input: UpdateUserInput! @spread): User @update
    }
    
    input CreateUserInput {
        name: String
        tasks: CreateTaskRelation
    }
    
    input CreateTaskRelation {
        create: [CreateTaskInput!]
    }
    
    input CreateTaskInput {
        name: String
    }
        
    input UpdateUserInput {
        id: ID!
        name: String
        tasks: UpdateTaskRelation
    }
    
    input UpdateTaskRelation {
        create: [CreateTaskInput!]
        update: [UpdateTaskInput!]
        delete: [ID!]
    }
    
    input UpdateTaskInput {
        id: ID!
        name: String
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
    public function itCanCreateWithNewHasMany(): void
    {
        $this->graphQL('
        mutation {
            createUser(input: {
                name: "foo"
                tasks: {
                    create: [{
                        name: "bar"
                    }]
                }
            }) {
                id
                name
                tasks {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createUser' => [
                    'id' => '1',
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => '1',
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanCreateHasMany(): void
    {
        factory(User::class)->create();

        $this->graphQL('
        mutation {
            updateUser(input: {
                id: 1
                name: "foo"
                tasks: {
                    create: [{
                        name: "bar"
                    }]
                }
            }) {
                id
                name
                tasks {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateUser' => [
                    'id' => '1',
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => '1',
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanUpdateHasMany(): void
    {
        factory(User::class)
            ->create()
            ->tasks()
            ->save(
                factory(Task::class)->create()
            );

        $this->graphQL('
        mutation {
            updateUser(input: {
                id: 1
                name: "foo"
                tasks: {
                    update: [{
                        id: 1
                        name: "bar"
                    }]
                }
            }) {
                id
                name
                tasks {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateUser' => [
                    'id' => '1',
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => '1',
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanDeleteHasMany(): void
    {
        factory(User::class)
            ->create()
            ->tasks()
            ->save(
                factory(Task::class)->create()
            );

        $this->graphQL('
        mutation {
            updateUser(input: {
                id: 1
                name: "foo"
                tasks: {
                    delete: [1]
                }
            }) {
                id
                name
                tasks {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateUser' => [
                    'id' => '1',
                    'name' => 'foo',
                    'tasks' => [],
                ],
            ],
        ]);
    }
}
