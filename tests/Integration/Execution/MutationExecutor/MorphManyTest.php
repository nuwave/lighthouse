<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Hour;
use Tests\Utils\Models\Task;

class MorphManyTest extends DBTestCase
{
    protected $schema = '
    type Task {
        id: ID!
        name: String!
        hours: [Hour!]!
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
        hours: CreateHourRelation
    }
    
    input CreateHourRelation {
        create: [CreateHourInput!]!
    }
    
    input CreateHourInput {
        weekday: Int
    }
    
    input UpdateTaskInput {
        id: ID!
        name: String
        hours: UpdateHourRelation
    }
    
    input UpdateHourRelation {
        create: [CreateHourInput!]
        update: [UpdateHourInput!]
        delete: [ID!]
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
    public function itCanCreateWithNewMorphMany(): void
    {
        $this->graphQL('
        mutation {
            createTask(input: {
                name: "foo"
                hours: {
                    create: [{
                        weekday: 3
                    }]
                }
            }) {
                id
                name
                hours {
                    weekday
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'hours' => [
                        [
                            'weekday' => 3,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanUpdateWithNewMorphMany(): void
    {
        factory(Task::class)->create();

        $this->graphQL('
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                hours: {
                    create: [{
                        weekday: 3
                    }]
                }
            }) {
                id
                name
                hours {
                    weekday
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'hours' => [
                        [
                            'weekday' => 3,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanUpdateAndUpdateMorphMany(): void
    {
        factory(Task::class)
            ->create()
            ->hours()
            ->save(
                factory(Hour::class)->create()
            );

        $this->graphQL('
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                hours: {
                    update: [{
                        id: 1
                        weekday: 3
                    }]
                }
            }) {
                id
                name
                hours {
                    weekday
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'hours' => [
                        [
                            'weekday' => 3,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanUpdateAndDeleteMorphMany(): void
    {
        factory(Task::class)
            ->create()
            ->hours()
            ->save(
                factory(Hour::class)->create()
            );

        $this->graphQL('
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                hours: {
                    delete: [1]
                }
            }) {
                id
                name
                hours {
                    weekday
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'hours' => [],
                ],
            ],
        ]);
    }
}
