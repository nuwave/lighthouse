<?php

namespace Tests\Integration\Schema\Directives\Fields\CreateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class BelongsToTest extends DBTestCase
{
    protected $schema = '
    type Mutation {
        createTask(input: CreateTaskInput!): Task @create(flatten: true)
    }
    
    type Task {
        id: ID!
        name: String!
        user: User @belongsTo
    }
    
    type User {
        id: ID!
        name: String!
    }
    
    input CreateTaskInput {
        name: String
        user: CreateUserRelation
    }
    
    input CreateUserRelation {
        connect: ID
        create: CreateUserInput
        update: UpdateUserInput
    }
    
    input CreateUserInput {
        name: String!
    }
    
    input UpdateUserInput {
        id: ID!
        name: String
    }
    ';

    public function setUp(): void
    {
        parent::setUp();

        $this->schema .= $this->placeholderQuery();
    }

    /**
     * @test
     */
    public function itCanCreateAndConnectWithBelongsTo(): void
    {
        factory(User::class)->create();

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
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanCreateWithNewBelongsTo(): void
    {
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
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanCreateAndUpdateBelongsTo(): void
    {
        factory(User::class)->create([
            'name' => 'foo'
        ]);

        $this->query('
        mutation {
            createTask(input: {
                name: "foo"
                user: {
                    update: {
                        id: 1
                        name: "bar"
                    }
                }
            }) {
                id
                name
                user {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1',
                        'name' => 'bar',
                    ],
                ],
            ],
        ]);
    }
}
