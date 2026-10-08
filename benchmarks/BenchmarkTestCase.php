<?php declare(strict_types=1);

namespace Benchmarks;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Allows reusing test setup and helpers for benchmarks.
 */
final class BenchmarkTestCase extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Execute a GraphQL query.
     *
     * @param  array<string, mixed>  $variables
     * @param  array<string, mixed>  $extraParams
     * @param  array<string, mixed>  $headers
     * @param  array<string, string>  $routeParams
     */
    public function graphql(
        string $query,
        array $variables = [],
        array $extraParams = [],
        array $headers = [],
        array $routeParams = [],
    ): TestResponse {
        return parent::graphQL(
            $query,
            $variables,
            $extraParams,
            $headers,
            $routeParams,
        );
    }

    public function app(): Application
    {
        return $this->app;
    }

    public function setSchema(string $schema): void
    {
        $this->schema = $schema;
    }
}
