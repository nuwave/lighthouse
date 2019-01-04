<?php

namespace Tests\Integration\Schema\Directives\Fields\CreateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\Task;

class HasManyTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateWithNewHasMany()
    {
        $schema = '
        type Task {
            id: ID!
            name: String!
        }
        
        type User {
            id: ID!
            name: String
            tasks: [Task!]! @hasMany
        }
        
        type Mutation {
            createUser(input: CreateUserInput!): User @create(flatten: true)
        }
        
        input CreateUserInput {
            name: String
            tasks: CreateTaskRelation
        }
        
        input CreateTaskRelation {
            create: [CreateTaskInput!]
        }
        
        input CreateTaskInput {
            name: String
            user: ID
        }
        '.$this->placeholderQuery();
        $query = '
        mutation {
            createUser(input: {
                name: "foo"
                tasks: {
                    create: [{
                        name: "bar"
                    }]
                }
            }) {
                id
                name
                tasks {
                    id
                    name
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame('1', Arr::get($result, 'data.createUser.id'));
        $this->assertSame('foo', Arr::get($result, 'data.createUser.name'));
        $this->assertSame('1', Arr::get($result, 'data.createUser.tasks.0.id'));
        $this->assertSame('bar', Arr::get($result, 'data.createUser.tasks.0.name'));
    }
}
