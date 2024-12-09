<?php declare(strict_types=1);

namespace Tests\Unit\Subscriptions\Broadcasters;

use Illuminate\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Subscriptions\BroadcastDriverManager;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\PusherBroadcaster;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Psr\Log\LoggerInterface;
use Tests\EnablesSubscriptionServiceProvider;
use Tests\TestCase;

final class PusherBroadcasterTest extends TestCase
{
    use EnablesSubscriptionServiceProvider;

    public function testPusherUsesLoggerInterface(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        // Minimum cases: "trigger POST"
        $logger
            ->expects($this->atLeast(1))
            ->method('log');

        $this->app->bind(LoggerInterface::class, static fn (): LoggerInterface => $logger);

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

        $this->app->bind(LoggerInterface::class, static fn (): LoggerInterface => $logger);

        $config = $this->app->make(ConfigRepository::class);
        $config->set('broadcasting.connections.pusher.log', false);

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->channel = 'test-123';

        $this->broadcast($subscriber);
    }

    /** @param  \Nuwave\Lighthouse\Subscriptions\Subscriber&\PHPUnit\Framework\MockObject\MockObject  $subscriber */
    private function broadcast(object $subscriber): void
    {
        $broadcastDriverManager = $this->app->make(BroadcastDriverManager::class);

        $pusherBroadcaster = $broadcastDriverManager->driver('pusher');
        assert($pusherBroadcaster instanceof PusherBroadcaster);

        $pusherBroadcaster->broadcast($subscriber, 'foo');
    }
}
