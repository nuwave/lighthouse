<?php

namespace Nuwave\Relay\Tests;

class GraphQLSchemaTest extends TestCase
{
    /**
     * @test
     */
    public function itCanManuallyAddTypesToSchema()
    {
        dd(app('graphql')->addType('foo'));
        // TODO: Write test
    }

    /**
     * @test
     */
    public function itCanAddTypesWithConfigFile()
    {
        // TODO: Write test
    }
}
