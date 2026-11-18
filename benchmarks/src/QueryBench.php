<?php declare(strict_types=1);

namespace Benchmarks;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tally\Attribute\Before;

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
        assert(is_string($routeName), 'Lighthouse configures its route name as a string.');
        $this->graphQLEndpoint = route($routeName);
    }

    /**
     * Execute a GraphQL query.
     *
     * @param  array<string, mixed>  $variables
     * @param  array<string, mixed>  $extraParams
     *
     * @return TestResponse<Response>
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

    #[Before]
    public function setPerformanceTuning(bool $removeFieldMiddleware, bool $queryCache, bool $validationCache): void
    {
        $this->setUp();

        $configRepository = $this->config();

        if ($removeFieldMiddleware) {
            $configRepository->set('lighthouse.field_middleware', []);
        }

        $configRepository->set('lighthouse.query_cache.enable', $queryCache);
        $configRepository->set('lighthouse.validation_cache.enable', $validationCache);
    }

    /** @return array<string, array{bool, bool, bool}> */
    public static function providePerformanceTuning(): array
    {
        return [
            'nothing' => [false, false, false],
            'query cache' => [false, true, false],
            'query + validation cache' => [false, true, true],
            'everything' => [true, true, true],
        ];
    }
}
