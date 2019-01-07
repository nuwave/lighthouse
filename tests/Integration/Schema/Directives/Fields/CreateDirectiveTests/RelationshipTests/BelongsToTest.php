<?php

namespace Tests\Integration\Schema\Directives\Fields\CreateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class BelongsToTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateAndConnectWithBelongsTo(): void
    {
        factory(User::class)->create();

        $this->schema = '
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

        $this->query('
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
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1'
                    ]
                ]
            ]
        ]);
    }

    /**
     * @test
     */
    public function itCanCreateWithNewBelongsTo(): void
    {
        $this->schema = '
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

        $this->query('
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
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1'
                    ]
                ]
            ]
        ]);
    }
}
