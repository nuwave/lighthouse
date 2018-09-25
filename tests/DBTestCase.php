<?php

namespace Tests;

class DBTestCase extends TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->loadMigrationsFrom(['--path' => __DIR__.'/database/migrations']);
        $this->withFactories(__DIR__.'/database/factories');
        $this->artisan('migrate');
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $connection = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ];

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', $connection);
    }
}
