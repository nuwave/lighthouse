<?php

namespace Tests\Integration\Schema\Directives\Fields\CreateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\User;

class MorphOneTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateWithNewMorphOne()
    {
        factory(User::class)->create();

        $schema = '
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
        $query = '
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
        ';
        $result = $this->execute($schema, $query);
        $this->assertSame('1', Arr::get($result, 'data.createTask.id'));
        $this->assertSame('foo', Arr::get($result, 'data.createTask.name'));
        $this->assertSame(3, Arr::get($result, 'data.createTask.hour.weekday'));
    }
}
