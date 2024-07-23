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

        $routeName = config('lighthouse.route.name');
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
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $config = $app->make(ConfigRepository::class);
        $config->set('lighthouse.field_middleware', []);
    }
}
