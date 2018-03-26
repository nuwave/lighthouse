<?php

namespace Tests;

use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
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
     * @param \Illuminate\Foundation\Application $app
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
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('lighthouse.directives', []);
        $app['config']->set('lighthouse.schema.register', null);
        $app['config']->set('lighthouse.global_id_field', '_id');

        $app['config']->set(
            'lighthouse.namespaces.scalars',
            'Tests\\Utils\\Scalars'
        );

        $app['config']->set(
            'lighthouse.namespaces.queries',
            'Tests\\Utils\\Mutations'
        );

        $app['config']->set(
            'lighthouse.namespaces.mutations',
            'Tests\\Utils\\Mutations'
        );

        $app['config']->set(
            'lighthouse.namespaces.models',
            'Tests\\Utils\\Models'
        );
    }

    /**
     * Load schema from directory.
     *
     * @param string $schema
     *
     * @return string
     */
    protected function loadSchema($schema = 'schema.graphql')
    {
        return file_get_contents(__DIR__."/Utils/Schemas/{$schema}");
    }

    /**
     * Get parsed schema.
     *
     * @param string $schema
     *
     * @return \GraphQL\Language\AST\DocumentNode
     */
    protected function parseSchema($schema = 'schema.graphql')
    {
        return Parser::parse($this->loadSchema($schema));
    }

    /**
     * Parse raw schema.
     *
     * @param string $schema
     *
     * @return \GraphQL\Language\AST\DocumentNode
     */
    protected function parse(string $schema)
    {
        return Parser::parse($schema);
    }

    /**
     * Execute query/mutation.
     *
     * @param string $schema
     * @param string $query
     * @param string $lighthouse
     * @param array  $variables
     *
     * @return \GraphQL\Executor\ExecutionResult
     */
    protected function execute($schema, $query, $lighthouse = false, $variables = [])
    {
        if ($lighthouse) {
            $node = file_get_contents(realpath(__DIR__.'/../assets/node.graphql'));
            $lighthouse = file_get_contents(realpath(__DIR__.'/../assets/schema.graphql'));
            $schema = $node."\n".$lighthouse."\n".$schema;
        }

        return Executor::execute(schema()->build($schema), $this->parse($query));
    }

    /**
     * Store file contents.
     *
     * @param string $fileName
     * @param string $contents
     *
     * @return string
     */
    protected function store($fileName, $contents)
    {
        $path = __DIR__.'/storage/'.$fileName;

        if (file_exists(__DIR__.'/storage/'.$fileName)) {
            unlink($path);
        }

        file_put_contents($path, $contents);

        return $path;
    }
}
