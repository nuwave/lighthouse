<?php

namespace Tests\Integration\Subscriptions;

use Tests\TestCase;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Exceptions\InvalidDriverException;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster;
use Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\PusherBroadcaster;

class BroadcastManagerTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Subscriptions\BroadcastManager
     */
    protected $broadcastManager;

    protected function getPackageProviders($app)
    {
        return array_merge(
            parent::getPackageProviders($app),
            [SubscriptionServiceProvider::class]
        );
    }

    /**
     * Set up test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->broadcastManager = app(BroadcastManager::class);
    }

    /**
     * @test
     */
    public function itCanResolveDrivers(): void
    {
        $pusherDriver = $this->broadcastManager->driver('pusher');
        $this->assertInstanceOf(PusherBroadcaster::class, $pusherDriver);

        $logDriver = $this->broadcastManager->driver('log');
        $this->assertInstanceOf(LogBroadcaster::class, $logDriver);
    }

    /**
     * @test
     */
    public function itCanExtendBroadcastManager(): void
    {
        $broadcasterConfig = [];

        $broadcaster = new class() implements Broadcaster {
            public function authorized(Request $request)
            {
                //
            }

            public function unauthorized(Request $request)
            {
                //
            }

            public function hook(Request $request)
            {
                //
            }

            public function broadcast(Subscriber $subscriber, array $data)
            {
                //
            }
        };

        $this->broadcastManager->extend('foo', function ($app, array $config) use (&$broadcasterConfig, $broadcaster): Broadcaster {
            $broadcasterConfig = $config;

            return $broadcaster;
        });

        /** @var Broadcaster $broadcaster */
        $resolvedBroadcaster = $this->broadcastManager->driver('foo');

        $this->assertSame(['driver' => 'foo'], $broadcasterConfig);

        $this->assertSame(
            $broadcaster,
            $resolvedBroadcaster
        );
    }

    /**
     * @test
     */
    public function itThrowsIfDriverDoesNotImplementInterface(): void
    {
        $this->broadcastManager->extend('foo', function () {
            return new class {
                //
            };
        });

        $this->expectException(InvalidDriverException::class);

        $this->broadcastManager->driver('foo');
    }
}
