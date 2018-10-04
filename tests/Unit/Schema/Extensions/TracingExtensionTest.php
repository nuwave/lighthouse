<?php

namespace Tests\Unit\Schema\Extensions;

use Tests\TestCase;
use Tests\Utils\Models\User;
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
