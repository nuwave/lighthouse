<?php

namespace Tests\Unit;

use GraphQL\Type\Schema;
use Tests\TestCase;

class GraphQLTest extends TestCase
{
    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $path = $this->store('schema.graphql', '
            type User {
                id: ID!
                created_at: String!
                updated_at: String!
            }
            type Query {
                user: User! @field(class: "Tests\\\Unit\\\GraphQLTest" method: "user")
            }
        ');

        $app['config']->set('lighthouse.schema.register', $path);
    }

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

        $expected = ['data' => [
            'user' => [
                'id' => 1,
                'created_at' => now()->format('Y-m-d'),
                'updated_at' => now()->format('Y-m-d'),
            ],
        ]];

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
