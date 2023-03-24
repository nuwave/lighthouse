<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Testing;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;

/**
 * Sets up the environment for testing subscriptions.
 */
trait TestsSubscriptions
{
    protected function setUpTestsSubscriptions(): void
    {
        $app = Container::getInstance();

        $config = $app->make(ConfigRepository::class);
        $config->set('lighthouse.subscriptions.queue_broadcasts', false);
        $config->set('lighthouse.subscriptions.storage', 'array');
        $config->set('lighthouse.subscriptions.storage_ttl', null);

        // binding an instance to the container, so it can be spied on
        $app->bind(Broadcaster::class, static fn (ConfigRepository $config): \Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster => new LogBroadcaster(
            $config->get('lighthouse.subscriptions.broadcasters.log'),
        ));

        $broadcastManager = $app->make(BroadcastManager::class);
        assert($broadcastManager instanceof BroadcastManager);

        // adding a custom driver which is a spied version of log driver
        $broadcastManager->extend('mock', fn () => $this->spy(LogBroadcaster::class)->makePartial());

        // set the custom driver as the default driver
        $config->set('lighthouse.subscriptions.broadcaster', 'mock');
    }
}
