<?php

namespace Tests\Unit\Subscriptions\Broadcasters;

use Illuminate\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use Tests\TestsSubscriptions;

class PusherBroadcasterTest extends TestCase
{
    use TestsSubscriptions;

    public function testPusherUsesLoggerInterface(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        // Minimum cases: "create_curl", "trigger POST", "exec_curl response"
        $logger
            ->expects($this->atLeast(3))
            ->method('log');

        $this->app->bind(LoggerInterface::class, function () use ($logger) {
            return $logger;
        });

        $config = $this->app->make(ConfigRepository::class);
        $config->set('broadcasting.connections.pusher.log', true);

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->channel = 'test-123';

        $broadcastManager = $this->app->make(BroadcastManager::class);
        $pusherBroadcaster = $broadcastManager->driver('pusher');
        $pusherBroadcaster->broadcast($subscriber, 'foo');
    }

    public function testPusherNeverUsesLoggerInterface(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $logger
            ->expects($this->never())
            ->method('log');

        $this->app->bind(LoggerInterface::class, function () use ($logger) {
            return $logger;
        });

        $config = $this->app->make(ConfigRepository::class);
        $config->set('broadcasting.connections.pusher.log', false);

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->channel = 'test-123';

        $broadcastManager = $this->app->make(BroadcastManager::class);
        $pusherBroadcaster = $broadcastManager->driver('pusher');
        $pusherBroadcaster->broadcast($subscriber, 'foo');
    }
}
