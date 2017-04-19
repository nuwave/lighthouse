<?php

namespace Nuwave\Lighthouse\Tests;

class DBTestCase extends TestCase
{
    /**
     * Set up the test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $this->loadMigrationsFrom([
            '--database' => 'testing',
            '--realpath' => realpath(__DIR__.'/Support/migrations'),
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
        $app['config']->set('database.connections.testing', [
            'driver' => env('DB_CONNECTION', 'mysql'),
            'host' => env('DB_HOST', 'localhost'),
            'database' => env('DB_DATABASE', 'lighthouse'),
            'username' => env('DB_USERNAME', 'lighthouse'),
            'password' => env('DB_PASSWORD', ''),
            'charset'  => 'utf8',
            'collation' => 'utf8_unicode_ci',
        ]);
    }
}
