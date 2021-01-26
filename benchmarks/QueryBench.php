<?php

namespace Benchmarks;

use Exception;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Laravel\Scout\ScoutServiceProvider;
use Nuwave\Lighthouse\GlobalId\GlobalIdServiceProvider;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use Nuwave\Lighthouse\OrderBy\OrderByServiceProvider;
use Nuwave\Lighthouse\Pagination\PaginationServiceProvider;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\SoftDeletes\SoftDeletesServiceProvider;
use Nuwave\Lighthouse\Testing\TestSchemaProvider;
use Nuwave\Lighthouse\Validation\ValidationServiceProvider;
use Orchestra\Testbench\TestCase;
use Tests\Utils\Policies\AuthServiceProvider;

/**
 * @BeforeMethods({"setUp"})
 */
abstract class QueryBench extends TestCase
{
    use MakesHttpRequests;

    /**
     * Schema, that will be loaded before request.
     *
     * @var string
     */
    protected $schema;

    /**
     * Cached graphQL endpoint.
     *
     * @var string
     */
    protected $graphQLEndpoint;

    public function setUp(): void
    {
        parent::setUp();

        $this->app->bind(
            SchemaSourceProvider::class,
            function (): TestSchemaProvider {
                if (! isset($this->schema)) {
                    throw new Exception('Missing test schema, provide one by setting $this->schema.');
                }

                return new TestSchemaProvider($this->schema);
            }
        );

        $this->app
            ->make(GraphQL::class)
            ->prepSchema();

        $this->graphQLEndpoint = route(config('lighthouse.route.name'));
    }

    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array<class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AuthServiceProvider::class,
            ScoutServiceProvider::class,

            // Lighthouse's own
            LighthouseServiceProvider::class,
            GlobalIdServiceProvider::class,
            OrderByServiceProvider::class,
            PaginationServiceProvider::class,
            SoftDeletesServiceProvider::class,
            ValidationServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app->make(ConfigRepository::class);

        $config->set('lighthouse.debug', false);
        $config->set('lighthouse.field_middleware', []);

        $config->set(
            'lighthouse.subscriptions',
            [
                'storage' => 'array',
                'broadcaster' => 'log',
            ]
        );

        $config->set('lighthouse.guard', null);
        $config->set('app.debug', false);
    }

    /**
     * Execute a query as if it was sent as a request to the server.
     *
     * @param  string  $query  The GraphQL query to send
     * @param  array<string, mixed>  $variables  The variables to include in the query
     * @param  array<string, mixed>  $extraParams  Extra parameters to add to the JSON payload
     * @return \Illuminate\Testing\TestResponse
     */
    protected function graphQL(string $query, array $variables = [], array $extraParams = [])
    {
        $params = ['query' => $query];

        if ($variables) {
            $params += ['variables' => $variables];
        }

        $params += $extraParams;

        return $this->postJson(
            $this->graphQLEndpoint,
            $params
        );
    }
}
