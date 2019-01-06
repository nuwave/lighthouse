<?php

namespace Tests\Integration\Schema\Directives\Fields\CreateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\User;

class MorphManyTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateWithNewMorphMany(): void
    {
        factory(User::class)->create();

        $this->schema = '
        type Task {
            id: ID!
            name: String!
            hours: [Hour]
        }
        
        type Hour {
            weekday: Int
        }
        
        type Mutation {
            createTask(input: CreateTaskInput!): Task @create(flatten: true)
        }
        
        input CreateHourRelation {
            create: [CreateHourInput!]!
        }
        
        input CreateTaskInput {
            name: String!
            user_id: ID!
            hours: CreateHourRelation
        }
        
        input CreateHourInput {
            from: String
            to: String
            weekday: Int
        }
        '.$this->placeholderQuery();

        $this->query('
        mutation {
            createTask(input: {
                name: "foo"
                user_id: 1
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
                            'weekday' => 3
                        ]
                    ]
                ]
            ]
        ]);
    }
}
