<?php

namespace Nuwave\Lighthouse\Tests;

use Nuwave\Lighthouse\Tests\TestCase;

class DBTestCase extends TestCase
{
    /**
     * Set up the test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--database' => 'testing',
            '--realpath' => realpath(__DIR__.'/Support/migrations')
        ]);
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
    }
}
