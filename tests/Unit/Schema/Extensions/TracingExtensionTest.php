<?php

namespace Tests\Unit\Schema\Extensions;

use Tests\TestCase;
use Illuminate\Support\Arr;
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
        $result = $this->query('
        {
            foo
        }
        ');

        $this->assertArrayHasKey('tracing', Arr::get($result, 'extensions'));
        $this->assertArrayHasKey('resolvers', Arr::get($result, 'extensions.tracing.execution'));
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
        $this->assertArrayHasKey('tracing', Arr::get($result[0], 'extensions'));
        $this->assertArrayHasKey('resolvers', Arr::get($result[0], 'extensions.tracing.execution'));

        $this->assertArrayHasKey('tracing', Arr::get($result[1], 'extensions'));
        $this->assertArrayHasKey('resolvers', Arr::get($result[1], 'extensions.tracing.execution'));

        $this->assertEquals(
            Arr::get($result[0], 'extensions.tracing.startTime'),
            Arr::get($result[1], 'extensions.tracing.startTime')
        );

        $this->assertNotEquals(
            Arr::get($result[0], 'extensions.tracing.endTime'),
            Arr::get($result[1], 'extensions.tracing.endTime')
        );

        $this->assertCount(1, Arr::get($result[0], 'extensions.tracing.execution.resolvers'));
        $this->assertCount(1, Arr::get($result[1], 'extensions.tracing.execution.resolvers'));
    }

    public function resolve()
    {
        usleep(20000); // 20 milliseconds
        return 'bar';
    }
}
