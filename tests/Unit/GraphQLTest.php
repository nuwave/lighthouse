<?php

namespace Tests\Unit;

use Illuminate\Support\Arr;
use Tests\TestCase;
use GraphQL\Type\Schema;
use GraphQL\Error\Debug;

class GraphQLTest extends TestCase
{
    protected $schema = '
    type User {
        id: ID!
        created_at: String!
        updated_at: String!
    }
    
    type Query {
        user: User! @field(resolver: "Tests\\\Unit\\\GraphQLTest@user")
    }
    ';

    /**
     * @test
     */
    public function itCanBuildGraphQLSchema()
    {
        $schema = graphql()->prepSchema();

        $this->assertInstanceOf(Schema::class, $schema);
    }

    /**
     * @test
     */
    public function itCanExecuteQuery()
    {
        $query = '
        {
            user {
                id
                created_at
                updated_at
            }
        }
        ';
        $result = graphql()->executeQuery($query)->toArray(Debug::RETHROW_INTERNAL_EXCEPTIONS);

        $expected = [
            'data' => [
                'user' => [
                    'id' => 1,
                    'created_at' => now()->format('Y-m-d'),
                    'updated_at' => now()->format('Y-m-d'),
                ],
            ],
        ];
        $this->assertEquals($expected, $result);
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
        $result = graphql()->executeQuery($query)->toArray(Debug::RETHROW_INTERNAL_EXCEPTIONS);

        $expected = [
            'data' => [
                'user' => [
                    'id' => 1,
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function itRejectsInvalidQuery()
    {
        $query = '
        {
            nonExistingField
        }
        ';
        $result = graphql()->executeQuery($query)->toArray();

        $this->assertContains('nonExistingField', Arr::get($result, 'errors.0.message'));
    }

    public function user($root, array $args, $context, $info): array
    {
        return [
            'id' => 1,
            'created_at' => now()->format('Y-m-d'),
            'updated_at' => now()->format('Y-m-d'),
        ];
    }
}
