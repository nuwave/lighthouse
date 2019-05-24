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
    }
    
    input CreateHourInput {
        hourable_type: String!
        hourable_id: Int!
        from: String
        to: String
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
    public function itCanCreateAndConnectWithMorphTo(): void
    {
        factory(Task::class)->create(['name' => 'first_task']);

        $this->graphQL('
        mutation {
            createHour(input: {
                hourable_type: "Tests\\\Utils\\\Models\\\Task"
                hourable_id: 1
                weekday: 2
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
}
