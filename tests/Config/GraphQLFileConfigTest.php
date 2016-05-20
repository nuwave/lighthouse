<?php

namespace Nuwave\Relay\Tests\Config;

use Nuwave\Relay\Tests\TestCase;

class GraphQLFileConfigTest extends TestCase
{
    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('relay.schema.register', __DIR__ . '/../Support/schema.php');
    }

    /**
     * @test
     */
    public function itCanRegisterTypesWithSchemaFile()
    {
        $this->assertEquals(app('graphql')->getType('foo'), 'bar');
        $this->assertEquals(app('graphql')->getQuery('bar'), 'baz');
    }
}
