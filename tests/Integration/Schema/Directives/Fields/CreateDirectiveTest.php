<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Illuminate\Support\Arr;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class CreateDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateFromFieldArguments()
    {
        $this->schema = '
        type Company {
            id: ID!
            name: String!
        }
        
        type Mutation {
            createCompany(name: String): Company @create
        }
        ' . $this->placeholderQuery();
        $query = '
        mutation {
            createCompany(name: "foo") {
                id
                name
            }
        }
        ';

        $this->query($query)->assertJson([
            'data' => [
                'createCompany' => [
                    'id' => '1',
                    'name' => 'foo'
                ]
            ]
        ]);
    }

    /**
     * @test
     */
    public function itCanCreateFromInputObject()
    {
        $this->schema = '
        type Company {
            id: ID!
            name: String!
        }
        
        type Mutation {
            createCompany(input: CreateCompanyInput!): Company @create(flatten: true)
        }
        
        input CreateCompanyInput {
            name: String
        }
        ' . $this->placeholderQuery();
        $query = '
        mutation {
            createCompany(input: {
                name: "foo"
            }) {
                id
                name
            }
        }
        ';

        $this->query($query)->assertJson([
            'data' => [
                'createCompany' => [
                    'id' => '1',
                    'name' => 'foo'
                ]
            ]
        ]);
    }

    /**
     * @test
     */
    public function itCanCreateWithBelongsTo()
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
        
        input CreateTaskInput {
            name: String
            user: ID
        }
        ' . $this->placeholderQuery();
        $query = '
        mutation {
            createTask(input: {
                name: "foo"
                user: 1
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ';

        $this->query($query)->assertJson([
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
    public function itCanCreateWithHasMany()
    {
        $this->schema = '
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
        ' . $this->placeholderQuery();
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

        $this->query($query)->assertJson([
            'data' => [
                'createUser' => [
                    'id' => '1',
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => '1',
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCreatesAnEntryWithDatabaseDefaultsAndReturnsItImmediately()
    {
        $this->schema = '
        type Mutation {
            createTag(name: String): Tag @create
        }
        
        type Tag {
            name: String!
            default_string: String!
        }
        ' . $this->placeholderQuery();
        $query = '
        mutation {
            createTag(name: "foobar"){
                name
                default_string
            }
        }
        ';

        $this->query($query)->assertJson([
            'data' => [
                'createTag' => [
                    'name' => 'foobar',
                    'default_string' => \CreateTestbenchTagsTable::DEFAULT_STRING,
                ]
            ]
        ]);
    }

    /**
     * @test
     */
    public function itDoesNotCreateWithFailingRelationship()
    {
        factory(Task::class)->create(['name' => 'Uniq']);

        $this->schema = '
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
        ' . $this->placeholderQuery();
        $query = '
        mutation {
            createUser(input: {
                name: "foo"
                tasks: {
                    create: [{
                        name: "Uniq"
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

        $this->query($query)
            ->assertJson([
                'data' => [
                    'createUser' => null,
                ],
            ])
            ->assertJsonCount(1, 'errors');

        $this->assertCount(1, User::all());
    }

    /**
     * @test
     */
    public function itDoesCreateWithFailingRelationshipAndTransactionParam()
    {
        factory(Task::class)->create(['name' => 'Uniq']);
        config(['lighthouse.transactional_mutations' => false]);
        $this->schema = '
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
        ' . $this->placeholderQuery();
        $query = '
        mutation {
            createUser(input: {
                name: "foo"
                tasks: {
                    create: [{
                        name: "Uniq"
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

        $this->query($query)
            // TODO allow partial success
//            ->assertJson([
//                'data' => [
//                    'createUser' => [
//                        'name' => 'foo',
//                        'tasks' => null,
//                    ],
//                ],
//            ])
            ->assertJsonCount(1, 'errors');

        $this->assertCount(2, User::all());
    }

    /**
     * @test
     */
    public function itDoesNotFailWhenPropertyNameMatchesModelsNativeMethods()
    {
        $this->schema = '
        type Task {
            id: ID!
            name: String!
            guard: String
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
            guard: String
        }
        ' . $this->placeholderQuery();
        $query = '
        mutation {
            createUser(input: {
                name: "foo"
                tasks: {
                    create: [{
                        name: "Uniq"
                        guard: "api"
                    }]
                }
            }) {
                tasks {
                    guard
                }
            }
        }
        ';

        $this->query($query)->assertJson([
            'data' => [
                'createUser' => [
                    'tasks' => [
                        [
                            'guard' => 'api'
                        ]
                    ]
                ]
            ]
        ]);
    }
}
