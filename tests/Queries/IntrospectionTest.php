<?php

namespace Nuwave\Lighthouse\Tests\Queries;

use Nuwave\Lighthouse\Tests\TestCase;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\UserType;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\TaskType;

class IntrospectionTest extends TestCase
{
    /**
     * @test
     * @group debug
     */
    public function itAcceptsIntrospectionForNodeInterface()
    {
        $query = '{
          __type(name: "Node") {
            name
            kind
            fields {
              name
              type {
                kind
                ofType {
                  name
                  kind
                }
              }
            }
          }
        }';

        $expected = [
            '__type' => [
                'name' => 'Node',
                'kind' => 'INTERFACE',
                'fields' => [[
                    'name' => 'id',
                    'type' => [
                        'kind' => 'NON_NULL',
                        'ofType' => [
                            'name' => 'ID',
                            'kind' => 'SCALAR'
                        ]
                    ]
                ]]
            ]
        ];

        $response = $this->executeQuery($query);

        $this->assertEquals($expected, $response['data']);
    }

    /**
     * @test
     */
    public function itAcceptsIntrospectionForConnections()
    {
        $query = '{
            __type(name: "TaskConnection") {
                fields {
                    name
                    type {
                        name
                        kind
                        ofType {
                            name
                            kind
                        }
                    }
                }
            }
        }';

        $expected = [
            '__type' => [
                'fields' => [[
                    'name' => 'pageInfo',
                    'type' => [
                        'name' => null,
                        'kind' => 'NON_NULL',
                        'ofType' => [
                            'name' => 'PageInfo',
                            'kind' => 'OBJECT',
                        ]
                    ]
                ], [
                    'name' => 'edges',
                    'type' => [
                        'name' => null,
                        'kind' => 'LIST',
                        'ofType' => [
                            'name' => 'TaskEdge',
                            'kind' => 'OBJECT'
                        ]
                    ]
                ]]
            ]
        ];

        $graphql = app('graphql');
        $graphql->schema()->type('user', UserType::class);
        $graphql->schema()->type('task', TaskType::class);

        $connection = $graphql->connection('task');
        $response = $this->executeQuery($query);

        $this->assertEquals($expected, $response['data']);
    }
}
