<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Hour;
use Tests\Utils\Models\Task;

class MorphOneTest extends DBTestCase
{
    protected $schema = '
    type Task {
        id: ID!
        name: String!
        hour: Hour
    }
    
    type Hour {
        weekday: Int
    }
    
    type Mutation {
        createTask(input: CreateTaskInput! @spread): Task @create
        updateTask(input: UpdateTaskInput! @spread): Task @update
    }
    
    input CreateTaskInput {
        name: String!
        hour: CreateHourRelation
    }
    
    input CreateHourRelation {
        create: CreateHourInput!
    }
    
    input CreateHourInput {
        weekday: Int
    }
    
    input UpdateTaskInput {
        id: ID!
        name: String
        hour: UpdateHourRelation
    }
    
    input UpdateHourRelation {
        create: CreateHourInput
        update: UpdateHourInput
        delete: ID
    }
    
    input UpdateHourInput {
        id: ID!
        weekday: Int
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
    public function itCanCreateWithNewMorphOne(): void
    {
        $this->graphQL('
        mutation {
            createTask(input: {
                name: "foo"
                hour: {
                    create: {
                        weekday: 3
                    }
                }
            }) {
                id
                name
                hour {
                    weekday
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'hour' => [
                        'weekday' => 3,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanUpdateWithNewMorphOne(): void
    {
        factory(Task::class)->create();

        $this->graphQL('
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                hour: {
                    create: {
                        weekday: 3
                    }
                }
            }) {
                id
                name
                hour {
                    weekday
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'hour' => [
                        'weekday' => 3,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanUpdateAndUpdateMorphOne(): void
    {
        factory(Task::class)
            ->create()
            ->hour()
            ->save(
                factory(Hour::class)->create()
            );

        $this->graphQL('
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                hour: {
                    update: {
                        id: 1
                        weekday: 3
                    }
                }
            }) {
                id
                name
                hour {
                    weekday
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'hour' => [
                        'weekday' => 3,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanUpdateAndDeleteMorphOne(): void
    {
        factory(Task::class)
            ->create()
            ->hour()
            ->save(
                factory(Hour::class)->create()
            );

        $this->graphQL('
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                hour: {
                    delete: 1
                }
            }) {
                id
                name
                hour {
                    weekday
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'hour' => null,
                ],
            ],
        ]);
    }
}
