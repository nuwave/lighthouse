<?php

namespace Nuwave\Lighthouse\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * Set up test environment.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->withFactories(__DIR__.'/Support/Database/Factories');
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            \Nuwave\Lighthouse\LaravelServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'GraphQL' => \Nuwave\Lighthouse\Support\Facades\GraphQLFacade::class,
        ];
    }

    /**
     * Execute query.
     *
     * @param  string $query
     * @param  array|null $variables
     * @return array
     */
    protected function executeQuery($query, array $variables = null)
    {
        return app('graphql')->execute($query, $variables);
    }
}
