<?php

namespace Tests;

use GraphQL\Type\Schema;
use GraphQL\Language\Parser;
use GraphQL\Executor\ExecutionResult;
use Laravel\Scout\ScoutServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Nuwave\Lighthouse\Support\Traits\CanFormatError;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Providers\LighthouseServiceProvider;

class TestCase extends BaseTestCase
{
    use CanFormatError;

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
     * @param bool   $lighthouse
     * @param array  $variables
     * @param bool   $format
     *
     * @return \GraphQL\Executor\ExecutionResult
     */
    protected function execute($schema, $query, $lighthouse = false, $variables = [], $format = false): ExecutionResult
    {
        if ($lighthouse) {
            $addDefaultSchema = file_get_contents(realpath(__DIR__.'/../assets/schema.graphql'));
            $schema = $addDefaultSchema."\n".$schema;
        }

        // The schema is injected into the runtime during execution of the query
        $this->schema = $schema;

        return graphql()->queryAndReturnResult($query, null, $variables);
    }

    /**
     * Execute query/mutation.
     *
     * @param string $schema
     * @param string $query
     * @param bool   $lighthouse
     * @param array  $variables
     *
     * @return \GraphQL\Executor\ExecutionResult
     */
    protected function executeAndFormat($schema, $query, $lighthouse = false, $variables = [])
    {
        $result = $this->execute($schema, $query, $lighthouse, $variables);

        if (! empty($result->errors)) {
            foreach ($result->errors as $error) {
                if ($error instanceof \Exception) {
                    info('GraphQL Error:', [
                        'code' => $error->getCode(),
                        'message' => $error->getMessage(),
                        'trace' => $error->getTraceAsString(),
                    ]);
                }
            }

            return [
                'data' => $result->data,
                'errors' => array_map([$this, 'formatError'], $result->errors),
            ];
        }

        return ['data' => $result->data];
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
