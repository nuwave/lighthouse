<?php

namespace Tests\Unit\Schema\Extensions;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\Extensions\TraceExtension;

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
            foo: String @field(resolver: \"{$resolver}\")
        }";

        $result = $this->execute($schema, '{ foo }', true);
        $this->assertArrayHasKey('tracing', $result->extensions);
        $this->assertArrayHasKey('resolvers', $result->extensions['tracing']);
    }

    public function resolve()
    {
        usleep(20000); // 20 milliseconds
        return 'bar';
    }
}
