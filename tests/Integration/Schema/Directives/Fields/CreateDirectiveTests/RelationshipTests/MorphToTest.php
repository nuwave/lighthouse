<?php

namespace Tests\Integration\Schema\Directives\Fields\CreateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\Task;

class MorphToTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateAndConnectWithMorphTo(): void
    {
        factory(Task::class)->create(['name' => 'first_task']);

        $this->schema = '
        type Task {
            id: ID
            name: String
            hour: Hour
        }
        
        type Hour {
            id: ID
            weekday: Int
            hourable: Task
        }
        
        type Mutation {
            createHour(input: CreateHourInput!): Hour @create(flatten: true)
        }
        
        input CreateHourInput {
            hourable_type: String!
            hourable_id: Int!
            from: String
            to: String
            weekday: Int
        }
        
        '.$this->placeholderQuery();

        $this->query('
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
                        'name' => 'first_task'
                    ]
                ]
            ]
        ]);
    }
}
