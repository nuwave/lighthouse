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
        upsertUser(input: UpsertUserInput! @spread): User @upsert
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
        upsert: [UpsertTaskInput!]
        delete: [ID!]
    }
    
    input UpdateTaskInput {
        id: ID!
        name: String
    }

    input UpsertUserInput {
        id: ID!
        name: String
        tasks: UpsertTaskRelation
    }

    input UpsertTaskRelation {
        create: [CreateTaskInput!]
        update: [UpdateTaskInput!]
        upsert: [UpsertTaskInput!]
        delete: [ID!]
    }

    input UpsertTaskInput {
        id: ID!
        name: String
    }
    '.self::PLACEHOLDER_QUERY;

    public function testCanCreateWithNewHasMany(): void
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

    public function testCanCreateUsingUpsertWithNewHasMany(): void
    {
        $this->graphQL('
        mutation {
            upsertUser(input: {
                id: 1
                name: "foo"
                tasks: {
                    upsert: [{
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
                'upsertUser' => [
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

    public function actionsOverExistingDataProvider()
    {
        yield ['Update action' => 'update'];
        yield ['Upsert action' => 'upsert'];
    }

    /**
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testCanCreateHasMany($action): void
    {
        factory(User::class)->create();

        $this->graphQL("
        mutation {
            ${action}User(input: {
                id: 1
                name: \"foo\"
                tasks: {
                    create: [{
                        name: \"bar\"
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
        ")->assertJson([
            'data' => [
                "${action}User" => [
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
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testCanUpdateHasMany($action): void
    {
        factory(User::class)
            ->create()
            ->tasks()
            ->save(
                factory(Task::class)->create()
            );

        $this->graphQL("
        mutation {
            ${action}User(input: {
                id: 1
                name: \"foo\"
                tasks: {
                    update: [{
                        id: 1
                        name: \"bar\"
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
        ")->assertJson([
            'data' => [
                "${action}User" => [
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
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testCanUpsertHasMany($action): void
    {
        factory(User::class)
            ->create()
            ->tasks()
            ->save(
                factory(Task::class)->create()
            );

        $this->graphQL("
        mutation {
            ${action}User(input: {
                id: 1
                name: \"foo\"
                tasks: {
                    upsert: [{
                        id: 1
                        name: \"bar\"
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
        ")->assertJson([
            'data' => [
                "${action}User" => [
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
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testCanDeleteHasMany($action): void
    {
        factory(User::class)
            ->create()
            ->tasks()
            ->save(
                factory(Task::class)->create()
            );

        $this->graphQL("
        mutation {
            ${action}User(input: {
                id: 1
                name: \"foo\"
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
        ")->assertJson([
            'data' => [
                "${action}User" => [
                    'id' => '1',
                    'name' => 'foo',
                    'tasks' => [],
                ],
            ],
        ]);
    }
}
