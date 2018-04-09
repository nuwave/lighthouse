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
        $this->artisan('migrate', ['--database' => 'lighthouse']);
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
            'driver' => 'mysql',
            'database' => 'lighthouse',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'prefix' => '',
            'username' => env('DB_USERNAME', 'testing'),
            'password' => env('DB_PASSWORD', ''),
        ];

        $app['config']->set('database.default', 'lighthouse');
        $app['config']->set('database.connections.lighthouse', $connection);
    }
}
