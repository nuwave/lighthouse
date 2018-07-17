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

    /**
     * @test
     */
    public function itCanAddTraceExtensionMetaToResult()
    {
        $resolver = addslashes(self::class).'@resolve';

        $schema = "
        type Query {
            foo: String! @field(resolver: \"{$resolver}\")
        }
        ";
        $query = '
        {
            foo
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertArrayHasKey('tracing', array_get($result, 'extensions'));
        $this->assertArrayHasKey('resolvers', array_get($result, 'extensions.tracing.execution'));
    }

    public function resolve()
    {
        usleep(20000); // 20 milliseconds
        return 'bar';
    }
}
