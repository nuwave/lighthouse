<?php

namespace Tests\Integration\Schema;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Execution\Utils\GlobalId;

class NodeInterfaceTest extends DBTestCase
{
    /**
     * @var array
     */
    protected $testTuples = [
        1 => [
            'id' => 1,
            'name' => 'foobar'
        ],
        2 => [
            'id' => 2,
            'name' => 'barbaz'
        ]
    ];

    /**
     * @test
     */
    public function itCanResolveNodes()
    {
        $schema = '
        type User @node(resolver: "Tests\\\Integration\\\Schema\\\NodeInterfaceTest@resolveNode") {
            name: String!
        }
        ' . $this->placeholderQuery();

        $firstGlobalId = GlobalId::encode('User', $this->testTuples[1]['id']);
        $secondGlobalId = GlobalId::encode('User', $this->testTuples[2]['id']);
        $query = '
        {
            first: node(id: "'.$firstGlobalId.'") {
                id
                ...on User {
                    name
                }
            }
            second: node(id: "'.$secondGlobalId.'") {
                id
                ...on User {
                    name
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);
        
        $this->assertSame([
            'first' => [
                'id' => $firstGlobalId,
                'name' => $this->testTuples[1]['name'],
            ],
            'second' => [
                'id' => $secondGlobalId,
                'name' => $this->testTuples[2]['name'],
            ],
        ], $result['data']);
    }

    public function resolveNode($id)
    {
        return $this->testTuples[$id];
    }

    /**
     * @test
     */
    public function itCanResolveModelsNodes()
    {
        $schema = '
        type User @model {
            name: String!
        }
        ' . $this->placeholderQuery();

        $user = factory(User::class)->create(
            ['name' => 'Sepp']
        );
        $globalId = GlobalId::encode('User', $user->getKey());
        $query = '
        {
            node(id: "'.$globalId.'") {
                id
                ...on User {
                    name
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);
    
        $this->assertSame([
            'node' => [
                'id' => $globalId,
                'name' => 'Sepp',
            ],
        ], $result['data']);
    }
}
