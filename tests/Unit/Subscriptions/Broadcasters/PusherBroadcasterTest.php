<?php

namespace Tests\Unit\Subscriptions\Broadcasters;

use Illuminate\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\PusherBroadcaster;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use Tests\TestsSubscriptions;

final class PusherBroadcasterTest extends TestCase
{
    use TestsSubscriptions;

    public function testPusherUsesLoggerInterface(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        // Minimum cases: "trigger POST"
        $logger
            ->expects($this->atLeast(1))
            ->method('log');

        $this->app->bind(LoggerInterface::class, static function () use ($logger): LoggerInterface {
            return $logger;
        });

        $config = $this->app->make(ConfigRepository::class);
        $config->set('broadcasting.connections.pusher.log', true);

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->channel = 'test-123';

        $this->broadcast($subscriber);
    }

    public function testPusherNeverUsesLoggerInterface(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $logger
            ->expects($this->never())
            ->method('log');

        $this->app->bind(LoggerInterface::class, static function () use ($logger): LoggerInterface {
            return $logger;
        });

        $config = $this->app->make(ConfigRepository::class);
        $config->set('broadcasting.connections.pusher.log', false);

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->channel = 'test-123';

        $this->broadcast($subscriber);
    }

    private function broadcast(Subscriber $subscriber): void
    {
        $broadcastManager = $this->app->make(BroadcastManager::class);
        $pusherBroadcaster = $broadcastManager->driver('pusher');
        assert($pusherBroadcaster instanceof PusherBroadcaster);

        $pusherBroadcaster->broadcast($subscriber, 'foo');
    }
}
