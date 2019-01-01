<?php

namespace Tests\Integration\Subscriptions;

use Tests\TestCase;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Exceptions\InvalidDriverException;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\PusherBroadcaster;

class BroadcastManagerTest extends TestCase implements GraphQLContext
{
    use HandlesSubscribers;

    /** @var BroadcastManager */
    protected $broadcastManager;

    /**
     * Set up test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->broadcastManager = app(BroadcastManager::class);
    }

    /**
     * @test
     */
    public function itCanResolveDrivers()
    {
        $pusherDriver = $this->broadcastManager->driver('pusher');
        $this->assertInstanceOf(PusherBroadcaster::class, $pusherDriver);

        $logDriver = $this->broadcastManager->driver('log');
        $this->assertInstanceOf(LogBroadcaster::class, $logDriver);
    }

    /**
     * @test
     */
    public function itCanExtendBroadcastManager()
    {
        $broadcasterConfig = [];

        $this->broadcastManager->extend('foo', function ($app, $config) use (&$broadcasterConfig) {
            $broadcasterConfig = $config;

            return new class() implements Broadcaster {
                public function authorized(Request $request)
                {
                }

                public function unauthorized(Request $request)
                {
                }

                public function hook(Request $request)
                {
                }

                public function broadcast(Subscriber $subscriber, array $data)
                {
                    return $data;
                }
            };
        });

        $data = ['foo' => 'bar'];
        $broadcaster = $this->broadcastManager->driver('foo');

        $this->assertSame(['driver' => 'foo'], $broadcasterConfig);
        $this->assertSame($data, $broadcaster->broadcast($this->subscriber(), $data));
    }

    /**
     * @test
     */
    public function itThrowsIfDriverDoesNotImplementInterface()
    {
        $this->broadcastManager->extend('foo', function ($app, $config) {
            return new class() {
            };
        });

        $this->expectException(InvalidDriverException::class);

        $this->broadcastManager->driver('foo');
    }
}
