<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;

class HasManyWithRenamedTest extends DBTestCase
{
    protected $schema = '
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
        upsertUser(input: UpsertUserInput! @spread): User @upsert
    }
    
    input UpsertUserInput {
        id: ID
        firstName: String @rename(attribute: "name")
        tasks: UpsertTaskRelation
    }

    input UpsertTaskRelation {
        upsert: [UpsertTaskInput!]
    }
    '.self::PLACEHOLDER_QUERY;

    public function testUpsertHasManyWithRenameDirective(): void
    {
        $this->schema .= '
            input UpsertTaskInput {
                id: ID
                name: String
            }
        ';

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertUser(input: {
                firstName: "foo"
                tasks: {
                    upsert: [{
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
GRAPHQL
        )->assertJson([
            'data' => [
                'upsertUser' => [
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

    public function testUpsertHasManyWithNestedRenameDirective(): void
    {
        $this->schema .= '
            input UpsertTaskInput {
                id: ID
                customName: String @rename(attribute: "name")
            }
        ';

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertUser(input: {
                firstName: "foo"
                tasks: {
                    upsert: [{
                        customName: "bar"
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
GRAPHQL
        )->assertJson([
            'data' => [
                'upsertUser' => [
                    'id' => '1',
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => '1',
                            'customName' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
