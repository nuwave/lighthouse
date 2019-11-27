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
        upsertTask(input: UpdateTaskInput! @spread): Task @upsert
    }
    
    input CreateTaskInput {
        name: String!
        hours: CreateHourRelation
    }
    
    input CreateHourRelation {
        create: [CreateHourInput!]
        upsert: [UpsertHourInput!]
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
        upsert: [UpsertHourInput!]
        delete: [ID!]
    }
    
    input UpdateHourInput {
        id: ID!
        weekday: Int
    }

    input UpsertTaskInput {
        id: ID!
        name: String
        hours: UpsertHourRelation
    }

    input UpsertHourRelation {
        create: [CreateHourInput!]
        update: [UpdateHourInput!]
        upsert: [UpsertHourInput!]
        delete: [ID!]
    }

    input UpsertHourInput {
        id: ID!
        weekday: Int
    }
    '.self::PLACEHOLDER_QUERY;

    public function testCanCreateWithNewMorphMany(): void
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

    public function testCanCreateWithUpsertMorphMany(): void
    {
        $this->graphQL('
        mutation {
            createTask(input: {
                name: "foo"
                hours: {
                    upsert: [{
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

    public function existingModelMutations()
    {
        return [
            ['Update action' => 'update'],
            ['Upsert action' => 'upsert'],
        ];
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateWithNewMorphMany(string $action): void
    {
        factory(Task::class)->create();

        $this->graphQL("
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
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
        ")->assertJson([
            'data' => [
                "${action}Task" => [
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
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateAndUpdateMorphMany(string $action): void
    {
        factory(Task::class)
            ->create()
            ->hours()
            ->save(
                factory(Hour::class)->create()
            );

        $this->graphQL("
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
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
        ")->assertJson([
            'data' => [
                "${action}Task" => [
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
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateAndUpsertMorphMany(string $action): void
    {
        factory(Task::class)
            ->create()
            ->hours()
            ->save(
                factory(Hour::class)->create()
            );

        $this->graphQL("
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
                hours: {
                    upsert: [{
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
        ")->assertJson([
            'data' => [
                "${action}Task" => [
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
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateAndDeleteMorphMany(string $action): void
    {
        factory(Task::class)
            ->create()
            ->hours()
            ->save(
                factory(Hour::class)->create()
            );

        $this->graphQL("
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
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
        ")->assertJson([
            'data' => [
                "${action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'hours' => [],
                ],
            ],
        ]);
    }
}
