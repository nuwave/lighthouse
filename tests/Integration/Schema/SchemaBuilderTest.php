<?php

namespace Tests\Integration\Schema;

use Tests\DBTestCase;

class SchemaBuilderTest extends DBTestCase
{
    /**
     * @test
     * @group failing
     */
    public function itCanResolveNodes()
    {
        $schema = '
        type User implements Node {
            _id: ID!
            name: String!
        }
        type Query {}
        ';

        $result = $this->execute($schema, '{ node(id: "foo") { _id } }', true);
        dd($result);
    }
}
