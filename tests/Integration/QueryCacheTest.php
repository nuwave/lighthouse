<?php declare(strict_types=1);

namespace Tests\Integration;

use GraphQL\Language\Parser;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Nuwave\Lighthouse\Cache\QueryCache;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

final class QueryCacheTest extends TestCase
{
    public function testEnabledWithDefaults(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', true);
        $config->set('lighthouse.validation_cache.enable', false);

        $event = Event::fake();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $event->assertDispatchedTimes(CacheMissed::class, 1);
        $event->assertDispatchedTimes(CacheHit::class, 0);
        $event->assertDispatchedTimes(KeyWritten::class, 1);

        // second request should be hit
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $event->assertDispatchedTimes(CacheMissed::class, 1);
        $event->assertDispatchedTimes(CacheHit::class, 1);
        $event->assertDispatchedTimes(KeyWritten::class, 1);
    }

    public function testDifferentQueriesHasDifferentKeys(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', true);
        $config->set('lighthouse.validation_cache.enable', false);

        $event = Event::fake();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $event->assertDispatchedTimes(CacheMissed::class, 2);
        $event->assertDispatchedTimes(CacheHit::class, 0);
        $event->assertDispatchedTimes(KeyWritten::class, 2);
    }

    public function testDisabled(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', false);
        $config->set('lighthouse.validation_cache.enable', false);

        $event = Event::fake();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $event->assertDispatchedTimes(CacheMissed::class, 0);
        $event->assertDispatchedTimes(CacheHit::class, 0);
        $event->assertDispatchedTimes(KeyWritten::class, 0);
    }

    public function testModeOPcache(): void
    {
        $filesystem = Storage::fake();

        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', true);
        $config->set('lighthouse.query_cache.mode', 'opcache');
        $config->set('lighthouse.query_cache.opcache_path', $filesystem->path(''));

        $expectedFilePath = 'lighthouse-query-ec859ac754fc185143d0daf8dcfa644b5e2271e219b9a8f80c3a6fdfb0ce67d0.php';
        $filesystem->assertMissing($expectedFilePath);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);
        $filesystem->assertExists($expectedFilePath);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);
        $filesystem->assertExists($expectedFilePath);
    }

    public function testModeHybrid(): void
    {
        $filesystem = Storage::fake();

        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', true);
        $config->set('lighthouse.query_cache.mode', 'hybrid');
        $config->set('lighthouse.query_cache.opcache_path', $filesystem->path(''));

        $event = Event::fake();

        $expectedFilePath = 'lighthouse-query-ec859ac754fc185143d0daf8dcfa644b5e2271e219b9a8f80c3a6fdfb0ce67d0.php';
        $filesystem->assertMissing($expectedFilePath);

        // First request should miss both caches and create both store and file cache
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $filesystem->assertExists($expectedFilePath);
        $event->assertDispatchedTimes(CacheMissed::class, 1);
        $event->assertDispatchedTimes(CacheHit::class, 0);
        $event->assertDispatchedTimes(KeyWritten::class, 1);

        // Second request should hit file cache (no store cache events)
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $filesystem->assertExists($expectedFilePath);
        // No additional cache events since file cache is used directly
        $event->assertDispatchedTimes(CacheMissed::class, 1);
        $event->assertDispatchedTimes(CacheHit::class, 0);
        $event->assertDispatchedTimes(KeyWritten::class, 1);
    }

    public function testModeHybridWithPopulatedCacheStoreButMissingFile(): void
    {
        $filesystem = Storage::fake();

        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', true);
        $config->set('lighthouse.query_cache.mode', 'hybrid');
        $config->set('lighthouse.query_cache.opcache_path', $filesystem->path(''));

        $query = /** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL;
        $queryHash = 'ec859ac754fc185143d0daf8dcfa644b5e2271e219b9a8f80c3a6fdfb0ce67d0';

        $expectedFilePath = "lighthouse-query-{$queryHash}.php";
        $filesystem->assertMissing($expectedFilePath);

        $queryInstance = Parser::parse($query);

        Cache::put(
            key: "lighthouse:query:{$queryHash}",
            value: QueryCache::opcacheFileContents($queryInstance),
        );

        $event = Event::fake();

        $this->graphQL($query)->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $filesystem->assertExists($expectedFilePath);
        $event->assertDispatchedTimes(CacheMissed::class, 0);
        $event->assertDispatchedTimes(CacheHit::class, 1);
        $event->assertDispatchedTimes(KeyWritten::class, 0);

        $this->graphQL($query)->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $filesystem->assertExists($expectedFilePath);
        $event->assertDispatchedTimes(CacheMissed::class, 0);
        $event->assertDispatchedTimes(CacheHit::class, 1);
        $event->assertDispatchedTimes(KeyWritten::class, 0);
    }
}
