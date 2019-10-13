<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;

class MorphToTest extends DBTestCase
{
    protected $schema = '
    type Task {
        id: ID
        name: String
    }
    
    type Hour {
        id: ID
        weekday: Int
        hourable: Task
    }
    
    type Mutation {
        createHour(input: CreateHourInput! @spread): Hour @create
        updateHour(input: UpdateHourInput! @spread): Hour @update
        upsertHour(input: UpsertHourInput! @spread): Hour @upsert
    }
    
    input CreateHourInput {
        from: String
        to: String
        weekday: Int
        hourable: CreateHourableOperations
    }
    
    input CreateHourableOperations {
        connect: ConnectHourableInput
    }

    input ConnectHourableInput {
        type: String!
        id: ID!
    }

    input UpdateHourInput {
        id: ID!
        from: String
        to: String
        weekday: Int
        hourable: UpdateHourableOperations
    }
    
    input UpdateHourableOperations {
        connect: ConnectHourableInput
        disconnect: Boolean
        delete: Boolean
    }
    
    input UpsertHourInput {
        id: ID!
        from: String
        to: String
        weekday: Int
        hourable: UpsertHourableOperations
    }

    input UpsertHourableOperations {
        connect: ConnectHourableInput
        disconnect: Boolean
        delete: Boolean
    }
    '.self::PLACEHOLDER_QUERY;

    public function testConnectsMorphTo(): void
    {
        factory(Task::class)->create(['name' => 'first_task']);

        $this->graphQL('
        mutation {
            createHour(input: {
                weekday: 2
                hourable: {
                    connect: {
                        type: "Tests\\\Utils\\\Models\\\Task"
                        id: 1
                    }
                }
            }) {
                id
                weekday
                hourable {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createHour' => [
                    'id' => '1',
                    'weekday' => 2,
                    'hourable' => [
                        'id' => '1',
                        'name' => 'first_task',
                    ],
                ],
            ],
        ]);
    }

    public function testConnectsMorphToWithUpsert(): void
    {
        factory(Task::class)->create(['name' => 'first_task']);

        $this->graphQL('
        mutation {
            upsertHour(input: {
                id: 1
                weekday: 2
                hourable: {
                    connect: {
                        type: "Tests\\\Utils\\\Models\\\Task"
                        id: 1
                    }
                }
            }) {
                id
                weekday
                hourable {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertHour' => [
                    'id' => '1',
                    'weekday' => 2,
                    'hourable' => [
                        'id' => '1',
                        'name' => 'first_task',
                    ],
                ],
            ],
        ]);
    }

    public function actionsOverExistingDataProvider()
    {
        return [
            ['Update action' => 'update'],
            ['Upsert action' => 'upsert'],
        ];
    }

    /**
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testDisconnectsMorphTo(string $action): void
    {
        /** @var \Tests\Utils\Models\Task $task */
        $task = factory(Task::class)->create(['name' => 'first_task']);
        $task->hour()->create([
            'weekday' => 1,
        ]);

        $this->graphQL("
        mutation {
            ${action}Hour(input: {
                id: 1
                weekday: 2
                hourable: {
                    disconnect: true
                }
            }) {
                weekday
                hourable {
                    id
                    name
                }
            }
        }
        ")->assertJson([
            'data' => [
                "${action}Hour" => [
                    'weekday' => 2,
                    'hourable' => null,
                ],
            ],
        ]);
    }

    /**
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testDeletesMorphTo(string $action): void
    {
        /** @var \Tests\Utils\Models\Task $task */
        $task = factory(Task::class)->create(['name' => 'first_task']);
        $task->hour()->create([
            'weekday' => 1,
        ]);

        $this->graphQL("
        mutation {
            ${action}Hour(input: {
                id: 1
                weekday: 2
                hourable: {
                    delete: true
                }
            }) {
                weekday
                hourable {
                    id
                    name
                }
            }
        }
        ")->assertJson([
            'data' => [
                "${action}Hour" => [
                    'weekday' => 2,
                    'hourable' => null,
                ],
            ],
        ]);

        $this->assertSame(
            0,
            Task::count()
        );
    }
}
