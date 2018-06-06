<?php


namespace Tests;


use Nuwave\Lighthouse\DigiaOnlineExecutor;
use Nuwave\Lighthouse\DigiaOnlineSchemaBuilder;
use Nuwave\Lighthouse\Executor;
use Nuwave\Lighthouse\Providers\LighthouseServiceProvider;
use Nuwave\Lighthouse\SchemaBuilder;
use Nuwave\Lighthouse\WebonyxSchemaBuilder;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [LighthouseServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return [
            SchemaBuilder::class => DigiaOnlineSchemaBuilder::class,
            Executor::class => DigiaOnlineExecutor::class,
        ];
    }
}
