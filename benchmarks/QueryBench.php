<?php declare(strict_types=1);

namespace Benchmarks;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Tests\TestCase;

/** @BeforeMethods({"setUp"}) */
abstract class QueryBench extends TestCase
{
    /** Cached graphQL endpoint. */
    protected string $graphQLEndpoint;

    public function __construct()
    {
        parent::__construct(static::class);
    }

    public function setUp(): void
    {
        parent::setUp();

        $config = $this->app->make(ConfigRepository::class);
        assert($config instanceof ConfigRepository);

        $routeName = $config->get('lighthouse.route.name');
        $this->graphQLEndpoint = route($routeName);
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

        $configRepository = $this->app->make(ConfigRepository::class);
        assert($configRepository instanceof ConfigRepository);

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
