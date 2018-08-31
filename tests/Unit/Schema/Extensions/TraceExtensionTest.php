<?php

namespace Tests\Unit\Schema\Extensions;

use Tests\TestCase;

class TraceExtensionTest extends TestCase
{
    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('lighthouse.extensions', ['tracing']);
    }

    protected $schema = <<<SCHEMA
type Query {
    foo: String! @field(resolver: "Tests\\\Unit\\\Schema\\\Extensions\\\TraceExtensionTest@resolve")
}
SCHEMA;

    /**
     * @test
     */
    public function itCanAddTraceExtensionMetaToResult()
    {
        $query = '
        {
            foo
        }
        ';
        $result = $this->postJson('graphql', ['query' => $query])->json();

        $this->assertArrayHasKey('tracing', array_get($result, 'extensions'));
        $this->assertArrayHasKey('resolvers', array_get($result, 'extensions.tracing.execution'));
    }

    public function resolve()
    {
        usleep(20000); // 20 milliseconds
        return 'bar';
    }
}
