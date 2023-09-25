<?php declare(strict_types=1);

namespace Tests\Unit\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Exceptions\InvalidDriverException;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\PusherBroadcaster;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Symfony\Component\HttpFoundation\Response;
use Tests\EnablesSubscriptionServiceProvider;
use Tests\TestCase;

final class BroadcastManagerTest extends TestCase
{
    use EnablesSubscriptionServiceProvider;

    /** @var \Nuwave\Lighthouse\Subscriptions\BroadcastManager */
    protected $broadcastManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->broadcastManager = $this->app->make(BroadcastManager::class);
    }

    public function testResolveDrivers(): void
    {
        $pusherDriver = $this->broadcastManager->driver('pusher');
        $this->assertInstanceOf(PusherBroadcaster::class, $pusherDriver);

        $logDriver = $this->broadcastManager->driver('log');
        $this->assertInstanceOf(LogBroadcaster::class, $logDriver);
    }

    public function testExtendBroadcastManager(): void
    {
        $broadcasterConfig = [];

        $broadcaster = new class() implements Broadcaster {
            public function authorized(Request $request): Response
            {
                return new Response();
            }

            public function unauthorized(Request $request): Response
            {
                return new Response();
            }

            public function hook(Request $request): Response
            {
                return new Response();
            }

            public function broadcast(Subscriber $subscriber, mixed $data): void {}
        };

        $this->broadcastManager->extend('foo', static function ($app, array $config) use (&$broadcasterConfig, $broadcaster): Broadcaster {
            $broadcasterConfig = $config;

            return $broadcaster;
        });

        $resolvedBroadcaster = $this->broadcastManager->driver('foo');
        assert($resolvedBroadcaster instanceof Broadcaster);

        $this->assertSame(['driver' => 'foo'], $broadcasterConfig);
        $this->assertSame($broadcaster, $resolvedBroadcaster);
    }

    public function testThrowsIfDriverDoesNotImplementInterface(): void
    {
        $this->broadcastManager->extend('foo', static fn (): object => new class() {});

        $this->expectException(InvalidDriverException::class);

        $this->broadcastManager->driver('foo');
    }
}
