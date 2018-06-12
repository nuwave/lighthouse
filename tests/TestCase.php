<?php


namespace Tests;


use Illuminate\Container\Container;
use Nuwave\Lighthouse\DigiaOnlineExecutor;
use Nuwave\Lighthouse\DigiaOnlineSchemaBuilder;
use Nuwave\Lighthouse\Executor;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Providers\LighthouseServiceProvider;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Support\Contracts\SchemaBuilder;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @var GraphQL */
    protected $graphql;

    protected function setUp()
    {
        parent::setUp();

        $directiveRegistry = new DirectiveRegistry();

        $this->graphql = new GraphQL(
            new DigiaOnlineSchemaBuilder($directiveRegistry),
            new DigiaOnlineExecutor(),
            $directiveRegistry
        );
    }
}
