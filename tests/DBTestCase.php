<?php

namespace Tests;

use Illuminate\Support\Facades\DB;

abstract class DBTestCase extends TestCase
{
    protected static $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! static::$migrated) {
            // We have to use this instead of --realpath as long as Laravel 5.5 is supported
            $this->app->setBasePath(__DIR__);
            $this->artisan('migrate:fresh', [
                '--path' => 'database/migrations',
            ]);

            static::$migrated = true;
        }

        // Ensure we start from a clean slate each time
        // We cannot use transactions, as they do not reset autoincrement
        foreach (DB::select('SHOW TABLES') as $table) {
            DB::table($table->Tables_in_test)->truncate();
        }

        $this->withFactories(__DIR__.'/database/factories');
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'database' => 'test',
            'host' => env('GITHUB_ACTIONS') ? '127.0.0.1' : 'mysql',
            'username' => 'root',
            'password' => env('GITHUB_ACTIONS') ? 'root' : '',
        ]);
    }
}
