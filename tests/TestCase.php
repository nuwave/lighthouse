<?php


namespace Tests;


use Nuwave\Lighthouse\Providers\LighthouseServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);
        
        // Load default config
        $app['config']->set('lighthouse', require __DIR__. '/../config/config.php');
    }
    
    protected function getPackageProviders($app)
    {
        return [LighthouseServiceProvider::class];
    }
}
