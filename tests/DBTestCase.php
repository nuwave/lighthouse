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

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->withFactories(__DIR__.'/database/factories');
        // This takes care of refreshing the database between tests
        // as we are using the in-memory SQLite db we do not need RefreshDatabase
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
