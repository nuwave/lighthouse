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
    }
    
    input CreateHourInput {
        from: String
        to: String
        weekday: Int
        hourable: CreateHourableOperations
    }
    
    input UpdateHourInput {
        id: ID!
        from: String
        to: String
        weekday: Int
        hourable: UpdateHourableOperations
    }
    
    input CreateHourableOperations {
        connect: ConnectHourableInput
    }
    
    input UpdateHourableOperations {
        connect: ConnectHourableInput
        disconnect: Boolean
        delete: Boolean
    }
    
    input ConnectHourableInput {
        type: String!
        id: ID!
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

    public function testDisconnectsMorphTo(): void
    {
        /** @var \Tests\Utils\Models\Task $task */
        $task = factory(Task::class)->create(['name' => 'first_task']);
        $task->hour()->create([
            'weekday' => 1,
        ]);

        $this->graphQL('
        mutation {
            updateHour(input: {
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
        ')->assertJson([
            'data' => [
                'updateHour' => [
                    'weekday' => 2,
                    'hourable' => null,
                ],
            ],
        ]);
    }

    public function testDeletesMorphTo(): void
    {
        /** @var \Tests\Utils\Models\Task $task */
        $task = factory(Task::class)->create(['name' => 'first_task']);
        $task->hour()->create([
            'weekday' => 1,
        ]);

        $this->graphQL('
        mutation {
            updateHour(input: {
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
        ')->assertJson([
            'data' => [
                'updateHour' => [
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
