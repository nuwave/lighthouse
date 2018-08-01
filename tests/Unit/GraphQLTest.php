<?php

namespace Tests\Unit;

use Tests\TestCase;
use GraphQL\Type\Schema;

class GraphQLTest extends TestCase
{
    protected $schema = '
    type User {
        id: ID!
        created_at: String!
        updated_at: String!
    }
    type Query {
        user: User! @field(class: "Tests\\\Unit\\\GraphQLTest" method: "user")
    }
    ';

    /**
     * @test
     */
    public function itCanBuildGraphQLSchema()
    {
        $schema = graphql()->buildSchema();

        $this->assertInstanceOf(Schema::class, $schema);
    }

    /**
     * @test
     */
    public function itCanExecuteQuery()
    {
        $query = '
        query User {
            user {
                id
                created_at
                updated_at
            }
        }
        ';

        $expected = [
            'data' => [
                'user' => [
                    'id' => 1,
                    'created_at' => now()->format('Y-m-d'),
                    'updated_at' => now()->format('Y-m-d'),
                ],
            ],
            'extensions' => [],
        ];

        $this->assertEquals($expected, graphql()->execute($query));
    }

    /**
     * @test
     */
    public function itCanExecuteQueryWithNamedOperation()
    {
        $query = '
        query User {
            user {
                id
                created_at
                updated_at
            }
        }
        query userOnlyId {
            user {
                id
            }
        }
        ';
        request()->merge(['operationName' => 'userOnlyId']);

        $expected = [
            'data' => [
                'user' => [
                    'id' => 1,
                ],
            ],
            'extensions' => [],
        ];

        $this->assertEquals($expected, graphql()->execute($query));
    }

    public function user($root, array $args, $context, $info)
    {
        return [
            'id' => 1,
            'created_at' => now()->format('Y-m-d'),
            'updated_at' => now()->format('Y-m-d'),
        ];
    }
}
