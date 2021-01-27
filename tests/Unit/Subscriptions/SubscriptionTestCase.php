<?php

namespace Tests\Unit\Subscriptions;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Redis\RedisServiceProvider;
use Illuminate\Support\Facades\Redis;
use Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider;
use Tests\TestCase;

class SubscriptionTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [
                RedisServiceProvider::class,
                SubscriptionServiceProvider::class,
            ]
        );
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app->make(ConfigRepository::class);

        $config->set('database.redis.default', [
            'url' => env('LIGHTHOUSE_TEST_REDIS_URL'),
            'host' => env('LIGHTHOUSE_TEST_REDIS_HOST', 'redis'),
            'password' => env('LIGHTHOUSE_TEST_REDIS_PASSWORD'),
            'port' => env('LIGHTHOUSE_TEST_REDIS_PORT', '6379'),
            'database' => env('LIGHTHOUSE_TEST_REDIS_DB', '0'),
        ]);

        $config->set('database.redis.options', [
            'prefix' => 'lighthouse-test-',
        ]);
    }

    protected function assertRedisHas(string $key): void
    {
        $this->assertGreaterThanOrEqual(1, Redis::exists($key));
    }

    protected function assertRedisMissing(string $key): void
    {
        $this->assertSame(0, Redis::exists($key));
    }
}
