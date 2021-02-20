<?php

namespace Tests\Unit\Subscriptions\Broadcasters;

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
        $logger->expects($this->atLeast(3))->method('log');

        // Bind the mocked logger instance instead of the actual logger
        app()->bind(LoggerInterface::class, function () use ($logger) {
            return $logger;
        });

        // Enable logging for pusher
        app('config')->set('broadcasting.connections.pusher.log', true);

        $pusherBroadcaster = app(BroadcastManager::class)->driver('pusher');
        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->channel = 'test-123';

        $pusherBroadcaster->broadcast($subscriber, 'foo');
    }

    public function testPusherNeverUsesLoggerInterface(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects($this->never())->method('log');

        // Bind the mocked logger instance instead of the actual logger
        app()->bind(LoggerInterface::class, function () use ($logger) {
            return $logger;
        });

        $pusherBroadcaster = app(BroadcastManager::class)->driver('pusher');
        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->channel = 'test-123';

        $pusherBroadcaster->broadcast($subscriber, 'foo');
    }
}
