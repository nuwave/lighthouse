<?php declare(strict_types=1);

namespace Benchmarks;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Testing\TestResponse;

/**
 * @BeforeMethods({"setUp"})
 */
abstract class QueryBench
{
    protected BenchmarkTestCase $testCase;

    /** GraphQL schema. */
    protected string $schema;

    /** Cached graphQL endpoint. */
    protected string $graphQLEndpoint;

    public function setUp(): void
    {
        $this->testCase = new BenchmarkTestCase('benchmark');
        $this->testCase->setSchema($this->schema);
        $this->testCase->setUp();

        $config = $this->config();
        $routeName = $config->get('lighthouse.route.name');
        $this->graphQLEndpoint = route($routeName);
    }

    /**
     * Execute a GraphQL query.
     *
     * @param  array<string, mixed>  $variables
     * @param  array<string, mixed>  $extraParams
     */
    protected function graphQL(string $query, array $variables = [], array $extraParams = []): TestResponse
    {
        return $this->testCase->graphQL($query, $variables, $extraParams);
    }

    protected function app(): Application
    {
        return $this->testCase->app();
    }

    protected function config(): ConfigRepository
    {
        return $this->app()->make(ConfigRepository::class);
    }

    /**
     * Return the full URL to the GraphQL endpoint.
     *
     * @param  array<string, string>  $routeParams  Parameters to pass to the route
     */
    protected function graphQLEndpointUrl(array $routeParams = []): string
    {
        return $this->graphQLEndpoint;
    }

    /**
     * Set up function with the performance tuning.
     *
     * @param  array{0: bool, 1: bool, 2: bool}  $params Performance tuning parameters
     */
    public function setPerformanceTuning(array $params): void
    {
        $this->setUp();

        $configRepository = $this->config();

        if ($params[0]) {
            $configRepository->set('lighthouse.field_middleware', []);
        }

        $configRepository->set('lighthouse.query_cache.enable', $params[1]);
        $configRepository->set('lighthouse.validation_cache.enable', $params[2]);
    }

    /**
     * Indexes:
     *  0: Remove all middlewares
     *  1: Enable query cache
     *  2: Enable validation cache
     *
     * @return array<string, array{0: bool, 1: bool, 2: bool}>
     */
    public function providePerformanceTuning(): array
    {
        return [
            'nothing' => [false, false, false],
            'query cache' => [false, true, false],
            'query + validation cache' => [false, true, true],
            'everything' => [true, true, true],
        ];
    }
}
