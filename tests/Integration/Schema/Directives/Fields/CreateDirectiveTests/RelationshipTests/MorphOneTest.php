<?php

namespace Tests\Integration\Schema\Directives\Fields\CreateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class MorphOneTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateWithNewMorphOne(): void
    {
        factory(User::class)->create();

        $this->schema = '
        type Task {
            id: ID!
            name: String!
            hour: Hour
        }
        
        type Hour {
            weekday: Int
        }
        
        type Mutation {
            createTask(input: CreateTaskInput!): Task @create(flatten: true)
        }
        
        input CreateHourRelation {
            create: CreateHourInput!
        }
        
        input CreateTaskInput {
            name: String!
            user_id: ID!
            hour: CreateHourRelation
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
                        'weekday' => 3
                    ]
                ]
            ]
        ]);
    }
}
