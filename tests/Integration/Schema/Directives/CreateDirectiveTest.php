<?php

namespace Tests\Integration\Schema\Directives;

use Tests\Constants;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class CreateDirectiveTest extends DBTestCase
{
    public function testCanCreateFromFieldArguments(): void
    {
        $this->schema .= '
        type Company {
            id: ID!
            name: String!
        }

        type Mutation {
            createCompany(name: String): Company @create
        }
        ';

        $this->graphQL('
        mutation {
            createCompany(name: "foo") {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'createCompany' => [
                    'id' => '1',
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testCanCreateFromInputObject(): void
    {
        $this->schema .= '
        type Company {
            id: ID!
            name: String!
        }

        type Mutation {
            createCompany(input: CreateCompanyInput! @spread): Company @create
        }

        input CreateCompanyInput {
            name: String
        }
        ';

        $this->graphQL('
        mutation {
            createCompany(input: {
                name: "foo"
            }) {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'createCompany' => [
                    'id' => '1',
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testCreatesAnEntryWithDatabaseDefaultsAndReturnsItImmediately(): void
    {
        $this->schema .= '
        type Mutation {
            createTag(name: String): Tag @create
        }

        type Tag {
            name: String!
            default_string: String!
        }
        ';

        $this->graphQL('
        mutation {
            createTag(name: "foobar"){
                name
                default_string
            }
        }
        ')->assertJson([
            'data' => [
                'createTag' => [
                    'name' => 'foobar',
                    'default_string' => Constants::TAGS_DEFAULT_STRING,
                ],
            ],
        ]);
    }

    public function testDoesNotCreateWithFailingRelationship(): void
    {
        factory(Task::class)->create(['name' => 'Uniq']);

        $this->app['config']->set('app.debug', false);

        $this->schema .= /* @lang GraphQL */'
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
            createUser(input: CreateUserInput! @spread): User @create
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
        ';

        $this->graphQL(/* @lang GraphQL */ '
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
        ')
            ->assertJson([
                'data' => [
                    'createUser' => null,
                ],
            ])
            ->assertJsonCount(1, 'errors');

        $this->assertCount(0, User::all());
    }

    public function testCreatesOnPartialFailureWithTransactionsDisabled(): void
    {
        factory(Task::class)->create(['name' => 'Uniq']);

        $this->app['config']->set('app.debug', false);
        config(['lighthouse.transactional_mutations' => false]);

        $this->schema .= /* @lang GraphQL */'
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
            createUser(input: CreateUserInput! @spread): User @create
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
        ';

        $this->graphQL(/* @lang GraphQL */ '
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
        ')
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

        $this->assertCount(1, User::all());
    }

    public function testDoesNotFailWhenPropertyNameMatchesModelsNativeMethods(): void
    {
        $this->schema .= '
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
            createUser(input: CreateUserInput! @spread): User @create
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
        ';

        $this->graphQL('
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
        ')->assertJson([
            'data' => [
                'createUser' => [
                    'tasks' => [
                        [
                            'guard' => 'api',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanCreateTwice(): void
    {
        $this->schema .= /* @lang GraphQL */ '
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
            createUser(input: CreateUserInput! @spread): User @create
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
        }
        ';

        $this->graphQL(/* @lang GraphQL */'
        mutation {
            createUser(input: {
                name: "foo"
                tasks: {
                    create: [{
                        name: "fooTask"
                    }]
                }
            }) {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'foo',
                ],
            ],
        ]);

        $this->graphQL(/* @lang GraphQL */'
        mutation {
            createUser(input: {
                name: "bar"
                tasks: {
                    create: [{
                        name: "barTask"
                    }]
                }
            }) {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'bar',
                ],
            ],
        ]);
    }
}
