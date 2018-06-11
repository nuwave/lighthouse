<?php


namespace Tests;


use Illuminate\Container\Container;
use Nuwave\Lighthouse\DigiaOnlineExecutor;
use Nuwave\Lighthouse\DigiaOnlineSchemaBuilder;
use Nuwave\Lighthouse\Executor;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Providers\LighthouseServiceProvider;
use Nuwave\Lighthouse\Support\Contracts\SchemaBuilder;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @var GraphQL */
    protected $graphql;

    protected $app;

    protected function setUp()
    {
        parent::setUp();
        $this->graphql = new GraphQL();
        $container = Container::getInstance();
        $this->app = $container;

        $container->bind(SchemaBuilder::class, DigiaOnlineSchemaBuilder::class);
        $container->bind(Executor::class, DigiaOnlineExecutor::class);
    }
}
