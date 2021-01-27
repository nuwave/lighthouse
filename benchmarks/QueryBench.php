<?php

namespace Benchmarks;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Tests\TestCase;

/**
 * @BeforeMethods({"setUp"})
 */
abstract class QueryBench extends TestCase
{
    /**
     * Cached graphQL endpoint.
     *
     * @var string
     */
    protected $graphQLEndpoint;

    public function setUp(): void
    {
        parent::setUp();

        $this->graphQLEndpoint = route(config('lighthouse.route.name'));
    }

    /**
     * Return the full URL to the GraphQL endpoint.
     */
    protected function graphQLEndpointUrl(): string
    {
        return $this->graphQLEndpoint;
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

        $config->set('lighthouse.field_middleware', []);
    }
}
