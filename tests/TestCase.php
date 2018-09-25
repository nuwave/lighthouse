<?php

namespace Tests;

use GraphQL\Error\Debug;
use GraphQL\Type\Schema;
use GraphQL\Executor\ExecutionResult;
use Laravel\Scout\ScoutServiceProvider;
use Tests\Utils\Policies\AuthServiceProvider;
use Orchestra\Database\ConsoleServiceProvider;
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
            AuthServiceProvider::class,
            ConsoleServiceProvider::class,
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

        $app['config']->set('lighthouse', [
            'namespaces' => [
                'scalars' => 'Tests\\Utils\\Scalars',
                'unions' => 'Tests\\Utils\\Unions',
                'queries' => 'Tests\\Utils\\Queries',
                'mutations' => 'Tests\\Utils\\Mutations',
                'models' => 'Tests\\Utils\\Models',
            ]
        ]);
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
    protected function executeQuery(string $schema, string $query, array $variables = []): ExecutionResult
    {
        // The schema is injected into the runtime during execution of the query
        $this->schema = $schema;

        return graphql()->executeQuery($query, null, $variables);
    }

    /**
     * @param string $schema
     * @param string $query
     * @param array $variables
     *
     * @return array
     */
    protected function executeWithoutDebug(string $schema, string $query, array $variables = []): array
    {
        return $this->executeQuery($schema, $query, $variables)->toArray();
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
        // For test execution, it is more convenient to throw Exceptions so they show up in the PHPUnit command line
        return $this->executeQuery($schema, $query, $variables)->toArray(Debug::RETHROW_INTERNAL_EXCEPTIONS);
    }

    /**
     * Convenience method to add a default Query, sometimes needed
     * because the Schema is invalid without it.
     *
     * @param string $schema
     *
     * @return \GraphQL\Type\Schema
     */
    protected function buildSchemaWithDefaultQuery(string $schema): Schema
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

        return graphql()->prepSchema();
    }
}
