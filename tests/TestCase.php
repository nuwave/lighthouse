<?php

namespace Nuwave\Lighthouse\Tests;

use GraphQL\Language\Parser;

use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
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
            \Nuwave\Lighthouse\Providers\LighthouseServiceProvider::class,
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
        return [];
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
        $app['config']->set(
            'lighthouse.namespaces.scalars',
            'Nuwave\\Lighthouse\\Tests\\Utils\\Scalars'
        );
    }

    /**
     * Load schema from directory.
     *
     * @param  string $schema
     * @return string
     */
    protected function loadSchema($schema = "schema.graphql")
    {
        return file_get_contents(__DIR__."/Utils/Schemas/{$schema}");
    }

    /**
     * Get parsed schema.
     *
     * @param  string $schema
     * @return \GraphQL\Language\AST\DocumentNode
     */
    protected function parseSchema($schema = "schema.graphql")
    {
        return Parser::parse($this->loadSchema($schema));
    }

    /**
     * Parse raw schema.
     *
     * @param  string $schema
     * @return \GraphQL\Language\AST\DocumentNode
     */
    protected function parse(string $schema)
    {
        return Parser::parse($schema);
    }
}
