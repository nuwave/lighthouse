<?php

namespace Tests\Integration\Schema;

use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class NodeTest extends DBTestCase
{
    use HandlesGlobalId;

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
            _id: ID!
            name: String!
        }
        type Query {}
        ';

        $globalId = $this->encodeGlobalId('User', $this->node['id']);
        $result = $this->execute($schema, '{ node(id: "'.$globalId.'") { name } }', true);

        $this->assertEquals($this->node['name'], array_get($result->data, 'node.name'));
    }

    /**
     * @test
     */
    public function itCanResolveModelsNodes()
    {
        $user = factory(User::class)->create();
        $globalId = $this->encodeGlobalId('User', $user->getKey());

        $schema = '
        type User @model {
            _id: ID!
            name: String!
        }
        type Query {}
        ';

        $result = $this->execute($schema, '{ node(id: "'.$globalId.'") { name } }', true);
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
            return schema()->instance('User');
        }
    }
}
