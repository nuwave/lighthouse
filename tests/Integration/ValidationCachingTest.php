<?php declare(strict_types=1);

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

final class ValidationCachingTest extends TestCase
{
    public function testEnabled(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', false);
        $config->set('lighthouse.validation_cache.enable', true);

        $event = Event::fake();

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $event->assertDispatchedTimes(CacheMissed::class, 1);
        $event->assertDispatchedTimes(CacheHit::class, 0);
        $event->assertDispatchedTimes(KeyWritten::class, 1);

        // second request should be hit
        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $event->assertDispatchedTimes(CacheMissed::class, 1);
        $event->assertDispatchedTimes(CacheHit::class, 1);
        $event->assertDispatchedTimes(KeyWritten::class, 1);
    }

    public function testDisabled(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', false);
        $config->set('lighthouse.validation_cache.enable', false);

        $event = Event::fake();

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $event->assertDispatchedTimes(CacheMissed::class, 0);
        $event->assertDispatchedTimes(CacheHit::class, 0);
        $event->assertDispatchedTimes(KeyWritten::class, 0);
    }

    public function testConfigMissing(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', false);
        $config->set('lighthouse.validation_cache', null);

        $event = Event::fake();

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $event->assertDispatchedTimes(CacheMissed::class, 0);
        $event->assertDispatchedTimes(CacheHit::class, 0);
        $event->assertDispatchedTimes(KeyWritten::class, 0);
    }

    public function testErrorsAreNotCached(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', false);
        $config->set('lighthouse.validation_cache.enable', true);

        $event = Event::fake();

        $this->graphQL(/** @lang GraphQL */ '
        {
            bar
        }
        ')->assertGraphQLErrorMessage('Cannot query field "bar" on type "Query".');

        $event->assertDispatchedTimes(CacheMissed::class, 1);
        $event->assertDispatchedTimes(CacheHit::class, 0);
        $event->assertDispatchedTimes(KeyWritten::class, 0);
    }

    public function testDifferentQueriesHasDifferentKeys(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', false);
        $config->set('lighthouse.validation_cache.enable', true);

        $event = Event::fake();

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
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

    public function testSameSchemaAndSameQueryHaveSameKeys(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', false);
        $config->set('lighthouse.validation_cache.enable', true);

        $event = Event::fake();

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $event->assertDispatchedTimes(CacheMissed::class, 1);
        $event->assertDispatchedTimes(CacheHit::class, 0);
        $event->assertDispatchedTimes(KeyWritten::class, 1);

        // refresh container, but keep the same cache
        $cacheFactory = $this->app->make(CacheFactory::class);
        $this->refreshApplication();
        $this->setUp();

        $this->app->instance(EventsDispatcher::class, $event);
        $this->app->instance(CacheFactory::class, $cacheFactory);

        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', false);
        $config->set('lighthouse.validation_cache.enable', true);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $event->assertDispatchedTimes(CacheMissed::class, 1);
        $event->assertDispatchedTimes(CacheHit::class, 1);
        $event->assertDispatchedTimes(KeyWritten::class, 1);
    }

    public function testDifferentSchemasHasDifferentKeys(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', false);
        $config->set('lighthouse.validation_cache.enable', true);

        $event = Event::fake();

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $event->assertDispatchedTimes(CacheMissed::class, 1);
        $event->assertDispatchedTimes(CacheHit::class, 0);
        $event->assertDispatchedTimes(KeyWritten::class, 1);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
type Query {
  bar: String
}

GRAPHQL;
        // refresh container, but keep the same cache
        $cacheFactory = $this->app->make(CacheFactory::class);

        $this->refreshApplication();
        $this->setUp();

        $this->app->instance(EventsDispatcher::class, $event);
        $this->app->instance(CacheFactory::class, $cacheFactory);

        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', false);
        $config->set('lighthouse.validation_cache.enable', true);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertGraphQLErrorMessage('Cannot query field "foo" on type "Query".');

        $event->assertDispatchedTimes(CacheMissed::class, 2);
        $event->assertDispatchedTimes(CacheHit::class, 0);
        $event->assertDispatchedTimes(KeyWritten::class, 1);
    }
}
