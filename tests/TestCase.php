<?php

namespace Tests;

use GraphQL\Type\Schema;
use GraphQL\Language\Parser;
use GraphQL\Executor\ExecutionResult;
use Laravel\Scout\ScoutServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Providers\LighthouseServiceProvider;

class TestCase extends BaseTestCase
{

    /**
     * This variable is injected the main GraphQL class
     * during execution of each test. It may be set either
     * for an entire test class or for a single test.
     *
     * @var string
     */
    protected $schema = '';

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
            LighthouseServiceProvider::class,
            ScoutServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app->bind(
            SchemaSourceProvider::class,
            function () {
                return new TestSchemaProvider($this->schema);
            }
        );

        $app['config']->set('lighthouse.directives', []);
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
     * @param array  $variables
     *
     * @return \GraphQL\Executor\ExecutionResult
     */
    protected function queryAndReturnResult(string $schema, string $query, array $variables = []): ExecutionResult
    {
        // The schema is injected into the runtime during execution of the query
        $this->schema = $schema;

        return graphql()->queryAndReturnResult($query, null, $variables);
    }

    /**
     * Execute and get the result as an array.
     *
     * @param string $schema
     * @param string $query
     * @param array  $variables
     *
     * @return array
     */
    protected function execute(string $schema, string $query, array $variables = []): array
    {
        $this->schema = $schema;

        return graphql()->execute($query, null, $variables);
    }

    /**
     * Convenience method to add a default Query, sometimes needed
     * because the Schema is invalid without it.
     *
     * @param string $schema
     *
     * @return \GraphQL\Type\Schema
     */
    protected function buildSchemaWithDefaultQuery($schema): Schema
    {
        return $this->buildSchemaFromString($schema.'
            type Query {
                dummy: String
            }
        ');
    }

    /**
     * @param string $schema
     *
     * @return \GraphQL\Type\Schema
     */
    protected function buildSchemaFromString(string $schema): Schema
    {
        $this->schema = $schema;

        return graphql()->buildSchema();
    }
}
