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
            $graphql->addType('foo', 'bar');
            $graphql->addQuery('bar', 'baz');
            $graphql->addMutation('bar', 'foo');
        });
    }

    /**
     * @test
     */
    public function itCanRegisterWithConfig()
    {
        $graphql = app('graphql');
        $this->assertEquals('foo', $graphql->getType('bar'));
        $this->assertEquals('bar', $graphql->getQuery('baz'));
        $this->assertEquals('bar', $graphql->getMutation('foo'));
    }
}
