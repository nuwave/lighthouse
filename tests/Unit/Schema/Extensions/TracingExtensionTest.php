<?php

namespace Tests\Unit\Schema\Extensions;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\Extensions\TracingExtension;

class TracingExtensionTest extends TestCase
{
    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('lighthouse.extensions', [TracingExtension::class]);
    }

    protected $schema = <<<SCHEMA
type Query {
    foo: String! @field(resolver: "Tests\\\Unit\\\Schema\\\Extensions\\\TracingExtensionTest@resolve")
}
SCHEMA;

    /**
     * @test
     */
    public function itCanAddTracingExtensionMetaToResult()
    {
        $result = $this->queryViaHttp('
        {
            foo
        }
        ');

        $this->assertArrayHasKey('tracing', array_get($result, 'extensions'));
        $this->assertArrayHasKey('resolvers', array_get($result, 'extensions.tracing.execution'));
    }

    /**
     * @test
     */
    public function itCanAddTracingExtensionMetaToBatchedResults()
    {
        $json = [
            ['query' => '{ foo }'],
            ['query' => '{ foo }'],
        ];

        $result = $this->postJson('graphql', $json)->json();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('tracing', array_get($result[0], 'extensions'));
        $this->assertArrayHasKey('resolvers', array_get($result[0], 'extensions.tracing.execution'));

        $this->assertArrayHasKey('tracing', array_get($result[1], 'extensions'));
        $this->assertArrayHasKey('resolvers', array_get($result[1], 'extensions.tracing.execution'));

        $this->assertEquals(
            array_get($result[0], 'extensions.tracing.startTime'),
            array_get($result[1], 'extensions.tracing.startTime')
        );

        $this->assertNotEquals(
            array_get($result[0], 'extensions.tracing.endTime'),
            array_get($result[1], 'extensions.tracing.endTime')
        );

        $this->assertCount(1, array_get($result[0], 'extensions.tracing.execution.resolvers'));
        $this->assertCount(1, array_get($result[1], 'extensions.tracing.execution.resolvers'));
    }

    public function resolve()
    {
        usleep(20000); // 20 milliseconds
        return 'bar';
    }
}
