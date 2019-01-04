<?php

namespace Tests\Integration\Schema\Directives\Fields\CreateDirectiveTests;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class CoreTest extends DBTestCase
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
        '.$this->placeholderQuery();
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
        '.$this->placeholderQuery();
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
        '.$this->placeholderQuery();
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

    /**
     * @test
     */
    public function itDoesNotCreateWithFailingRelationship()
    {
        factory(Task::class)->create(['name' => 'Uniq']);

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

        try {
            $this->execute($schema, $query);
        } catch (\Exception $err) {
            $this->assertCount(1, User::all());
        }
    }

    /**
     * @test
     */
    public function itDoesCreateWithFailingRelationshipAndTransactionParam()
    {
        factory(Task::class)->create(['name' => 'Uniq']);
        config(['lighthouse.transactional_mutations' => false]);
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
        try {
            $this->execute($schema, $query);
        } catch (\Exception $err) {
            $this->assertCount(2, User::all());
        }
    }

    /**
     * @test
     */
    public function itDoesNotFailWhenPropertyNameMatchesModelsNativeMethods()
    {
        $schema = '
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
        '.$this->placeholderQuery();
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
                id
                name
                tasks {
                    id
                    name
                    guard
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame('api', Arr::get($result, 'data.createUser.tasks.0.guard'));
    }
}
