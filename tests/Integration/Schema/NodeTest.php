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
                name: String!
            }
            
            type Query {}
        ';

        $globalId = $this->encodeGlobalId('User', $this->node['id']);
        $query = '
            {
                node(id: "'.$globalId.'") {
                    ... on User {
                        name
                    }
                }
            }
        ';
        $result = $this->execute($schema, $query, true);

        $this->assertEquals($this->node['name'], array_get($result->data, 'node.name'));
    }

    /**
     * This is used as a helper for the test above.
     *
     * @param $id
     *
     * @return array
     */
    public function resolveNode($id)
    {
        if ($this->node['id'] === $id) {
            return $this->node;
        }
    }

    /**
     * Helper for the test above.
     *
     * @param $value
     *
     * @return \GraphQL\Type\Definition\Type
     */
    public function resolveNodeType($value)
    {
        if (is_array($value) && isset($value['name'])) {
            return graphql()->types()->get('User');
        }
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
            
            type Query {}
        ';

        $user = factory(User::class)->create();
        $globalId = $this->encodeGlobalId('User', $user->getKey());

        $query = '
            {
                node(id: "'.$globalId.'") {
                    ... on User {
                        name
                    }
                }
            }
        ';

        $result = $this->execute($schema, $query, true);
        $this->assertEquals($user->name, array_get($result->data, 'node.name'));
    }
}
