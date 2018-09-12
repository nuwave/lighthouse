<?php

namespace Tests\Integration\Schema;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Execution\Utils\GlobalId;

class NodeTest extends DBTestCase
{
    protected $node = ['id' => '1', 'name' => 'foobar'];

    /**
     * @test
     */
    public function itCanResolveNodes()
    {
        $schema = '
        type User @node(
            resolver: "Tests\\\Integration\\\Schema\\\NodeTest@resolveNode"
            typeResolver: "Tests\\\Integration\\\Schema\\\NodeTest@resolveNodeType"
        ) {
            name: String!
        }
        
        type Query {
            dummy: Int
        }
        ';

        $globalId = GlobalId::encode('User', $this->node['id']);
        $query = '
        {
            node(id: "'.$globalId.'") {
                ...on User {
                    name
                }
            }
        }
        ';
        $result = $this->executeQuery($schema, $query);

        $this->assertEquals($this->node['name'], array_get($result->data, 'node.name'));
    }

    /**
     * @test
     */
    public function itCanResolveNodesWithDefaultTypeResolver()
    {
        $schema = '
        type User @node(
            resolver: "Tests\\\Integration\\\Schema\\\NodeTest@resolveNode"
        ) {
            name: String!
        }
        
        type Query {
            dummy: Int
        }
        ';

        $globalId = GlobalId::encode('User', $this->node['id']);
        $query = '
        {
            node(id: "'.$globalId.'") {
                ...on User {
                    name
                }
            }
        }
        ';
        $result = $this->executeQuery($schema, $query);

        $this->assertEquals($this->node['name'], array_get($result->data, 'node.name'));
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
        
        type Query {
            dummy: Int
        }
        ';

        $user = factory(User::class)->create();
        $globalId = GlobalId::encode('User', $user->getKey());
        $query = '
        {
            node(id: "'.$globalId.'") {
                ...on User {
                    name
                }
            }
        }
        ';

        $result = $this->executeQuery($schema, $query);
        $this->assertEquals($user->name, array_get($result->data, 'node.name'));
    }

    public function resolveNode($id)
    {
        if ($this->node['id'] == $id) {
            return $this->node;
        }
    }

    public function resolveNodeType($value)
    {
        if (is_array($value) && isset($value['name'])) {
            return resolve(TypeRegistry::class)->get('User');
        }
    }
}
