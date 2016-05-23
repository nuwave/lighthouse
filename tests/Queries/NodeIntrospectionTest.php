<?php

namespace Nuwave\Relay\Tests\Queries;

use Nuwave\Relay\Tests\TestCase;

class NodeIntrospectionTest extends TestCase
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
}
