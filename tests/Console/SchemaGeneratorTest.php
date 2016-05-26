<?php

namespace Nuwave\Relay\Tests\Console;

use Nuwave\Relay\Tests\TestCase;

class SchemaGeneratorTest extends TestCase
{
    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('relay.schema.output', __DIR__.'/../Support/storage/schema/schema.json');
    }

    /**
     * Resolve application Console Kernel implementation.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function resolveApplicationConsoleKernel($app)
    {
        $app->singleton('Illuminate\Contracts\Console\Kernel', 'Nuwave\Relay\Tests\Support\Console\Kernel');
    }

    /**
     * @test
     */
    public function itGeneratesSchemaFile()
    {
        $this->artisan('relay:schema');
    }
}
