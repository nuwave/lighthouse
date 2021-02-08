<?php

namespace Tests;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Redis;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRouter;

trait TestsRedis
{
    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
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

        $config->set('lighthouse.subscriptions', [
            'version' => 1,
            'storage' => 'redis',
            'broadcaster' => 'echo',
            'broadcasters' => [
                'echo' => [
                    'driver' => 'echo',
                    'routes' => SubscriptionRouter::class.'@echoRoutes',
                ],
            ],
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
