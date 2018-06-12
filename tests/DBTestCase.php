<?php

namespace Tests;



use Nuwave\Lighthouse\Providers\LighthouseServiceProvider;

use Orchestra\Testbench\TestCase as BaseTestCase;

class DBTestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp()
    {
        parent::setUp();
        
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->withFactories(__DIR__ . '/database/factories');
        $this->artisan('migrate', ['--database' => env('DB_DATABASE', 'lighthouse')]);
    }


    protected function getPackageProviders($app)
    {
        return [LighthouseServiceProvider::class];
    }
    
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('lighthouse', require __DIR__. '/../config/config.php');
        
        $connection = [
            'driver' => 'mysql',
            'database' => env('DB_DATABASE', 'lighthouse'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'prefix' => '',
            'username' => env('DB_USERNAME', 'testing'),
            'password' => env('DB_PASSWORD', ''),
        ];
        
        $app['config']->set('database.default', env('DB_DATABASE', 'lighthouse'));
        $app['config']->set('database.connections.' . env('DB_DATABASE', 'lighthouse'), $connection);
    }
}
