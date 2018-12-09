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
}
