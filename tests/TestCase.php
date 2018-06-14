<?php

namespace Tests;

use GraphQL\GraphQL;
use Laravel\Scout\ScoutServiceProvider;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
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
            ScoutServiceProvider::class,
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
     * Execute query/mutation.
     *
     * @param string $schema
     * @param string $query
     * @param bool   $addDefaultSchema
     *
     * @return \GraphQL\Executor\ExecutionResult
     */
    protected function execute($schema, $query, $addDefaultSchema = false)
    {
        if ($addDefaultSchema) {
            $addDefaultSchema = file_get_contents(realpath(__DIR__.'/../assets/schema.graphql'));
            $schema = $addDefaultSchema."\n".$schema;
        }

        return GraphQL::executeQuery($this->buildSchemaFromString($schema), $query);
    }

    /**
     * @param string $schema
     *
     * @return \GraphQL\Type\Schema
     */
    protected function buildSchemaFromString($schema)
    {
        return (new SchemaBuilder())->build(ASTBuilder::generate($schema));
    }

    /**
     * Convenience method to add a default Query, sometimes needed because the Schema is invalid without it.
     *
     * @param string $schema
     *
     * @return \GraphQL\Type\Schema
     */
    protected function buildSchemaWithDefaultQuery($schema)
    {
        return $this->buildSchemaFromString($schema.'
            type Query {
                dummy: String
            }
        ');
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
