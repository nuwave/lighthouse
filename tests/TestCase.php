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
            \Nuwave\Lighthouse\LighthouseServiceProvider::class,
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
}
