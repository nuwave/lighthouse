<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\Task;
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
    public function itCanCreateWithExistingBelongsTo()
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
            user_id: ID
        }
        '.$this->placeholderQuery();
        $query = '
        mutation {
            createTask(input: {
                name: "foo"
                user_id: 1
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

    /**
     * @test
     */
    public function itCanCreateWithHasOne()
    {
        factory(User::class)->create();

        $schema = '
        type Task {
            id: ID!
            name: String!
            post: Post @hasOne
        }
        
        type Post {
            id: ID!
            title: String!
            body: String!
        }
        
        type Mutation {
            createTask(input: CreateTaskInput!): Task @create(flatten: true)
        }
        
        input CreatePostRelation {
            create: CreatePostInput!
        }
        
        input CreatePostRelation {
            create: CreatePostInput!
        }
        
        input CreateTaskInput {
            name: String!
            user_id: ID!
            post: CreatePostRelation
        }
        
        input CreatePostInput {
            title: String!
            user_id: ID!
            body: String!
        }
        '.$this->placeholderQuery();
        $query = '
        mutation {
            createTask(input: {
                name: "foo"
                user_id: 1
                post: {
                    create: {
                        title: "bar"
                        user_id: 1
                        body: "foobar"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    body
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);
        $this->assertSame('1', Arr::get($result, 'data.createTask.id'));
        $this->assertSame('foo', Arr::get($result, 'data.createTask.name'));
        $this->assertSame('1', Arr::get($result, 'data.createTask.post.id'));
        $this->assertSame('foobar', Arr::get($result, 'data.createTask.post.body'));
    }

    /**
     * @test
     */
    public function itCanCreateWithMorphMany()
    {
        factory(User::class)->create();

        $schema = '
        type Task {
            id: ID!
            name: String!
            hours: [Hour]
        }
        
        type Hour {
            weekday: Int
        }
        
        type Mutation {
            createTask(input: CreateTaskInput!): Task @create(flatten: true)
        }
        
        
        input CreateHourRelation {
            create: [CreateHourInput!]!
        }
        
        input CreateTaskInput {
            name: String!
            user_id: ID!
            hours: CreateHourRelation
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
                hours: {
                    create: [{
                        weekday: 3
                    }]
                }
            }) {
                id
                name
                hours {
                    weekday
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);
        $this->assertSame('1', Arr::get($result, 'data.createTask.id'));
        $this->assertSame('foo', Arr::get($result, 'data.createTask.name'));
        $this->assertSame(3, Arr::get($result, 'data.createTask.hours.0.weekday'));
    }

    /**
     * @test
     */
    public function itCanCreateWithMorphOne()
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

    /**
     * @test
     */
    public function itCanCreateWithBelongsToMany()
    {
        $schema = '
        type Role {
            id: ID!
            name: String
            users: [User] @belongsToMany
        }
        
        type User {
            id: ID
            name: String
        }
        
        type Mutation {
            createRole(input: CreateRoleInput!): Role @create(flatten: true)
        }
        
        input CreateRoleInput {
            name: String
            users: CreateUserRelation
        }
        
        input CreateUserRelation {
            create: [CreateUserInput!]
        }
        
        input CreateUserInput {
            name: String
        }
        '.$this->placeholderQuery();
        $query = '
        mutation {
            createRole(input: {
                name: "foobar"
                users: {
                    create: [{
                        name: "bar"
                    },
                    {
                        name: "foo"
                    }]
                }
            }) {
                id
                name
                users {
                    id
                    name
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);
        $this->assertSame('1', Arr::get($result, 'data.createRole.id'));
        $this->assertSame('foobar', Arr::get($result, 'data.createRole.name'));
        $this->assertSame('1', Arr::get($result, 'data.createRole.users.0.id'));
        $this->assertSame('bar', Arr::get($result, 'data.createRole.users.0.name'));
    }
}
