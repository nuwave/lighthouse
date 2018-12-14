<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\User;

class CreateDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateFromFieldArguments()
    {
        $schema = '
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
        $result = $this->execute($schema, $query);

        $this->assertSame('1', Arr::get($result, 'data.createCompany.id'));
        $this->assertSame('foo', Arr::get($result, 'data.createCompany.name'));
    }

    /**
     * @test
     */
    public function itCanCreateFromInputObject()
    {
        $schema = '
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
        $result = $this->execute($schema, $query);

        $this->assertSame('1', Arr::get($result, 'data.createCompany.id'));
        $this->assertSame('foo', Arr::get($result, 'data.createCompany.name'));
    }

    /**
     * @test
     */
    public function itCanCreateWithBelongsTo()
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
        $result = $this->execute($schema, $query);

        $this->assertSame('1', Arr::get($result, 'data.createTask.id'));
        $this->assertSame('foo', Arr::get($result, 'data.createTask.name'));
        $this->assertSame('1', Arr::get($result, 'data.createTask.user.id'));
    }

    /**
     * @test
     */
    public function itCanCreateWithHasMany()
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
        $result = $this->execute($schema, $query);

        $this->assertSame('1', Arr::get($result, 'data.createUser.id'));
        $this->assertSame('foo', Arr::get($result, 'data.createUser.name'));
        $this->assertSame('1', Arr::get($result, 'data.createUser.tasks.0.id'));
        $this->assertSame('bar', Arr::get($result, 'data.createUser.tasks.0.name'));
    }

    /**
     * @test
     */
    public function itCreatesAnEntryWithDatabaseDefaultsAndReturnsItImmediately()
    {
        $schema = '
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
        $result = $this->execute($schema, $query);

        $this->assertSame([
            'name' => 'foobar',
            'default_string' => \CreateTestbenchTagsTable::DEFAULT_STRING,
        ], Arr::get($result, 'data.createTag'));
    }
}
