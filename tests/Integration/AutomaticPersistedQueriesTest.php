<?php declare(strict_types=1);

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

final class AutomaticPersistedQueriesTest extends TestCase
{
    public function testEnabled(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', true);
        $config->set('lighthouse.persisted_queries', true);

        $query = /** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL;

        $sha256 = hash('sha256', $query);

        $this->postGraphQL([
            'extensions' => [
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => $sha256,
                ],
            ],
        ])->assertJson([
            'errors' => [
                [
                    'message' => 'PersistedQueryNotFound',
                    'extensions' => [
                        'code' => 'PERSISTED_QUERY_NOT_FOUND',
                    ],
                ],
            ],
        ]);

        // run sending the query
        $this->postGraphQL([
            'query' => $query,
            'extensions' => [
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => $sha256,
                ],
            ],
        ])->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        // run without query, the query should be cached
        $this->postGraphQL([
            'extensions' => [
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => $sha256,
                ],
            ],
        ])->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);
    }

    public function testConfigDisabled(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', true);
        $config->set('lighthouse.persisted_queries', false);

        $query = /** @lang GraphQL */ '
        {
            foo
        }
        ';

        $sha256 = hash('sha256', $query);

        $this->graphQL(query: $query, extraParams: [
            'extensions' => [
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => $sha256,
                ],
            ],
        ])->assertGraphQLErrorFree();

        // run without query, the query should not be cached as it is disabled in the config
        $this->postGraphQL([
            'extensions' => [
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => $sha256,
                ],
            ],
        ])->assertJson([
            'errors' => [
                [
                    'message' => 'PersistedQueryNotSupported',
                    'extensions' => [
                        'code' => 'PERSISTED_QUERY_NOT_SUPPORTED',
                    ],
                ],
            ],
        ]);
    }

    public function testCacheDisabled(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', false);
        $config->set('lighthouse.persisted_queries', true);

        $query = /** @lang GraphQL */ '
        {
            foo
        }
        ';

        $sha256 = hash('sha256', $query);

        $this->graphQL(query: $query, extraParams: [
            'extensions' => [
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => $sha256,
                ],
            ],
        ])->assertGraphQLErrorFree();

        // run without query, the query should not be cached as query cache is disabled in the config
        $this->postGraphQL([
            'extensions' => [
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => $sha256,
                ],
            ],
        ])->assertJson([
            'errors' => [
                [
                    'message' => 'PersistedQueryNotSupported',
                    'extensions' => [
                        'code' => 'PERSISTED_QUERY_NOT_SUPPORTED',
                    ],
                ],
            ],
        ]);
    }
}
