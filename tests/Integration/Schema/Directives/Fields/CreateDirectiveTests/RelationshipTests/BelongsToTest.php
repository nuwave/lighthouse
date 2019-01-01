<?php

namespace Tests\Integration\Schema\Directives\Fields\CreateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\User;

class BelongsToTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateAndConnectWithBelongsTo()
    {
        factory(User::class)->create();

        $schema = '
        type Task {
            id: ID!
            name: String!
            user: User @belongsTo
        }
        
        type User {
            id: ID
        }
        
        type Mutation {
            createTask(input: CreateTaskInput!): Task @create(flatten: true)
        }
        
        input CreateUserRelation {
            connect: ID
        }
        
        input CreateTaskInput {
            name: String
            user: CreateUserRelation
        }
        '.$this->placeholderQuery();
        $query = '
        mutation {
            createTask(input: {
                name: "foo"
                user: {
                    connect: 1
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame('1', Arr::get($result, 'data.createTask.id'));
        $this->assertSame('foo', Arr::get($result, 'data.createTask.name'));
        $this->assertSame('1', Arr::get($result, 'data.createTask.user.id'));
    }

    /**
     * @test
     */
    public function itCanCreateWithNewBelongsTo()
    {
        $schema = '
        type Task {
            id: ID!
            name: String!
            user: User @belongsTo
        }
        
        type User {
            id: ID
        }
        
        type Mutation {
            createTask(input: CreateTaskInput!): Task @create(flatten: true)
        }
        
        input CreateUserRelation {
            create: CreateUserInput!
        }
        
        input CreateUserInput {
            name: String!
        }
        
        input CreateTaskInput {
            name: String
            user: CreateUserRelation
        }
        '.$this->placeholderQuery();
        $query = '
        mutation {
            createTask(input: {
                name: "foo"
                user: {
                    create: {
                        name: "New User"
                    }
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);
        $this->assertSame('1', Arr::get($result, 'data.createTask.id'));
        $this->assertSame('foo', Arr::get($result, 'data.createTask.name'));
        $this->assertSame('1', Arr::get($result, 'data.createTask.user.id'));
    }
}