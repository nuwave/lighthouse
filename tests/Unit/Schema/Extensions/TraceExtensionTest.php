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

        $app['config']->set('lighthouse.extensions', ['trace']);
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
        dd(app(TraceExtension::class)->toArray());
    }

    public function resolve()
    {
        usleep(20000); // 20 milliseconds
        return 'bar';
    }
}
