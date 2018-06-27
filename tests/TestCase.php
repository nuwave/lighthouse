<?php

namespace Tests;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use Laravel\Scout\ScoutServiceProvider;
use Nuwave\Lighthouse\Providers\LighthouseServiceProvider;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

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
     * @param bool $lighthouse
     * @param array $variables
     *
     * @return \GraphQL\Executor\ExecutionResult
     */
    protected function execute($schema, $query, $lighthouse = false, $variables = []): ExecutionResult
    {
        if ($lighthouse) {
            $addDefaultSchema = file_get_contents(realpath(__DIR__ . '/../assets/schema.graphql'));
            $schema = $addDefaultSchema . "\n" . $schema;
        }

        $this->schema = $schema;
        
        return graphql()->queryAndReturnResult($query, null, $variables);
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
        return $this->buildSchemaFromString($schema . '
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
