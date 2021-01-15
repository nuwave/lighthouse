<?php

namespace Tests\Integration\Subscriptions\EchoBroadcaster;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Redis;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRouter;
use Tests\Unit\Subscriptions\SubscriptionTestCase;

class EchoTestCase extends SubscriptionTestCase
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

        $config->set(
            'lighthouse.subscriptions',
            [
                'storage' => 'redis',
                'broadcaster' => 'echo',
                'broadcasters' => [
                    'echo' => [
                        'driver' => 'echo',
                        'routes' => SubscriptionRouter::class.'@echoRoutes',
                    ],
                ],
            ]
        );
    }
}
