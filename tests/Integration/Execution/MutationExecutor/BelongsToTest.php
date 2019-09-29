<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class BelongsToTest extends DBTestCase
{
    protected $schema = '
    type Task {
        id: ID!
        name: String!
        user: User @belongsTo
    }
    
    type User {
        id: ID!
        name: String!
    }
    
    type Mutation {
        createTask(input: CreateTaskInput! @spread): Task @create
        updateTask(input: UpdateTaskInput! @spread): Task @update
    }
    
    input CreateTaskInput {
        name: String
        user: CreateUserRelation
    }
    
    input CreateUserRelation {
        connect: ID
        create: CreateUserInput
        update: UpdateUserInput
    }
    
    input CreateUserInput {
        name: String!
    }
    
    input UpdateUserInput {
        id: ID!
        name: String
    }
    
    input UpdateTaskInput {
        id: ID!
        name: String
        user: UpdateUserRelation
    }
    
    input UpdateUserRelation {
        disconnect: Boolean
        delete: Boolean
    }
    ';

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema .= $this->placeholderQuery();
    }

    public function testCanCreateAndConnectWithBelongsTo(): void
    {
        factory(User::class)->create();

        $this->graphQL('
        mutation {
            createTask(input: {
                name: "foo"
                user: {
                    connect: 1
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }

    public function testCanCreateWithNewBelongsTo(): void
    {
        $this->graphQL('
        mutation {
            createTask(input: {
                name: "foo"
                user: {
                    create: {
                        name: "New User"
                    }
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }

    public function testCanCreateAndUpdateBelongsTo(): void
    {
        factory(User::class)->create([
            'name' => 'foo',
        ]);

        $this->graphQL('
        mutation {
            createTask(input: {
                name: "foo"
                user: {
                    update: {
                        id: 1
                        name: "bar"
                    }
                }
            }) {
                id
                name
                user {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1',
                        'name' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    public function testCanUpdateAndDisconnectBelongsTo(): void
    {
        factory(Task::class)->create();

        $this->graphQL('
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                user: {
                    disconnect: true
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => null,
                ],
            ],
        ]);

        $this->assertTrue(
            User::find(1)->exists,
            'Must not delete the second model.'
        );

        $this->assertNull(
            Task::find(1)->user,
            'Must disconnect the parent relationship.'
        );
    }

    public function testCanUpdateAndDeleteBelongsTo(): void
    {
        factory(Task::class)->create();

        $this->graphQL('
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                user: {
                    delete: true
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => null,
                ],
            ],
        ]);

        $this->assertNull(
            User::find(1),
            'This model should be deleted.'
        );

        $this->assertNull(
            Task::find(1)->user,
            'Must disconnect the parent relationship.'
        );
    }

    public function testDoesNotDeleteOrDisconnectOnFalsyValues(): void
    {
        factory(Task::class)->create();

        $this->graphQL('
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                user: {
                    delete: null
                    disconnect: false
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1',
                    ],
                ],
            ],
        ]);

        $this->assertSame(
            1,
            Task::find(1)->user->id,
            'The parent relationship remains untouched.'
        );
    }
}
