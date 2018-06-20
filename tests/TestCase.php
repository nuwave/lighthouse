<?php

namespace Tests;

use GraphQL\GraphQL;
use GraphQL\Language\Parser;
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
    protected function parse($schema)
    {
        return Parser::parse($schema);
    }
    
    /**
     * Execute query/mutation.
     *
     * @param string $schema
     * @param string $query
     * @param bool $lighthouse
     * @param array $variables
     *
     * @return \GraphQL\Executor\ExecutionResult
     */
    protected function execute($schema, $query, $lighthouse = false, $variables = [])
    {
        if ($lighthouse) {
            $addDefaultSchema = file_get_contents(realpath(__DIR__.'/../assets/schema.graphql'));
            $schema = $addDefaultSchema."\n".$schema;
        }

        return GraphQL::executeQuery(
            $this->buildSchemaFromString($schema),
            $query,
            null,
            null,
            $variables
        );
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
     * Convenience method to add a default Query, sometimes needed
     * because the Schema is invalid without it.
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
}
