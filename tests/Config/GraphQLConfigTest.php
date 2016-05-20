<?php

namespace Nuwave\Relay\Tests\Config;

use Nuwave\Relay\Tests\TestCase;

class GraphQLConfigTest extends TestCase
{
    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('relay.schema.register', function () {
            $graphql = app('graphql');
            $graphql->type('foo', 'bar');
        });
    }

    /**
     * @test
     */
    public function itCanRegisterTypesWithConfig()
    {
        $this->assertEquals(app('graphql')->getType('bar'), 'foo');
    }
}
