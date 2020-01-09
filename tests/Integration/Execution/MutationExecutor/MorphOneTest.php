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
        id: ID!
        weekday: Int
    }
    
    type Mutation {
        createTask(input: CreateTaskInput! @spread): Task @create
        updateTask(input: UpdateTaskInput! @spread): Task @update
        upsertTask(input: UpsertTaskInput! @spread): Task @upsert
    }
    
    input CreateTaskInput {
        name: String!
        hour: CreateHourRelation
    }
    
    input CreateHourRelation {
        create: CreateHourInput
        upsert: UpsertHourInput
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
        upsert: UpsertHourInput
        delete: ID
    }
    
    input UpdateHourInput {
        id: ID!
        weekday: Int
    }

    input UpsertTaskInput {
        id: ID
        name: String
        hour: UpsertHourRelation
    }

    input UpsertHourRelation {
        create: CreateHourInput
        update: UpdateHourInput
        upsert: UpsertHourInput
        delete: ID
    }

    input UpsertHourInput {
        id: ID
        weekday: Int
    }
    '.self::PLACEHOLDER_QUERY;

    public function testCanCreateWithNewMorphOne(): void
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

    public function testCanCreateWithUpsertMorphOne(): void
    {
        $this->graphQL('
        mutation {
            createTask(input: {
                name: "foo"
                hour: {
                    upsert: {
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

    public function testUpsertMorphOneWithoutId(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            upsertTask(input: {
                name: "foo"
                hour: {
                    upsert: {
                        weekday: 3
                    }
                }
            }) {
                id
                name
                hour {
                    id
                    weekday
                }
            }
        }
GRAPHQL
        )->assertJson([
            'data' => [
                'upsertTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'hour' => [
                        'id' => 1,
                        'weekday' => 3,
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
    public function testCanUpdateWithNewMorphOne(string $action): void
    {
        factory(Task::class)->create();

        $this->graphQL("
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
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
        ")->assertJson([
            'data' => [
                "${action}Task" => [
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
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateWithUpsertMorphOne(string $action): void
    {
        factory(Task::class)->create();

        $this->graphQL("
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
                hour: {
                    upsert: {
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
        ")->assertJson([
            'data' => [
                "${action}Task" => [
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
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateAndUpdateMorphOne(string $action): void
    {
        factory(Task::class)
            ->create()
            ->hour()
            ->save(
                factory(Hour::class)->create()
            );

        $this->graphQL("
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
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
        ")->assertJson([
            'data' => [
                "${action}Task" => [
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
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateAndDeleteMorphOne(string $action): void
    {
        factory(Task::class)
            ->create()
            ->hour()
            ->save(
                factory(Hour::class)->create()
            );

        $this->graphQL("
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
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
        ")->assertJson([
            'data' => [
                "${action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'hour' => null,
                ],
            ],
        ]);
    }
}
