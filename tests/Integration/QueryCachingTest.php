<?php declare(strict_types=1);

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

final class QueryCachingTest extends TestCase
{
    public function testEnabled(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', true);

        Event::fake();

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        Event::assertDispatchedTimes(CacheMissed::class, 1);
        Event::assertDispatchedTimes(CacheHit::class, 0);
        Event::assertDispatchedTimes(KeyWritten::class, 1);

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

        Event::assertDispatchedTimes(CacheMissed::class, 1);
        Event::assertDispatchedTimes(CacheHit::class, 1);
        Event::assertDispatchedTimes(KeyWritten::class, 1);
    }

    public function testDifferentQueriesHasDifferentKeys(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', true);

        Event::fake();

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

        Event::assertDispatchedTimes(CacheMissed::class, 2);
        Event::assertDispatchedTimes(CacheHit::class, 0);
        Event::assertDispatchedTimes(KeyWritten::class, 2);
    }

    public function testDisabled(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', false);

        Event::fake();

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        Event::assertDispatchedTimes(CacheMissed::class, 0);
        Event::assertDispatchedTimes(CacheHit::class, 0);
        Event::assertDispatchedTimes(KeyWritten::class, 0);
    }
}
