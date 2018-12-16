<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Pusher\Pusher;
use Pusher\PusherException;
use Nuwave\Lighthouse\Support\DriverManager;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\PusherBroadcaster;

/**
 * @method void                      broadcast(Subscriber $subscriber, array $data)
 * @method \Illuminate\Http\Response hook(\Illuminate\Http\Request $request)
 * @method \Illuminate\Http\Response authorized(\Illuminate\Http\Request $request)
 * @method \Illuminate\Http\Response unauthorized(\Illuminate\Http\Request $request)
 */
class BroadcastManager extends DriverManager
{
    /**
     * Get configuration key.
     *
     * @return string
     */
    protected function configKey(): string
    {
        return 'lighthouse.subscriptions.broadcasters';
    }

    /**
     * Get configuration driver key.
     *
     * @return string
     */
    protected function driverKey(): string
    {
        return 'lighthouse.subscriptions.broadcaster';
    }

    /**
     * The interface the driver should implement.
     *
     * @return string
     */
    protected function interface(): string
    {
        return Broadcaster::class;
    }

    /**
     * Create instance of pusher driver.
     *
     * @param array $config
     *
     * @throws PusherException
     *
     * @return PusherBroadcaster
     */
    protected function createPusherDriver(array $config): PusherBroadcaster
    {
        $pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            config('broadcasting.connections.pusher.options', [])
        );

        return new PusherBroadcaster($pusher);
    }

    /**
     * Create instance of log driver.
     *
     * @param array $config
     *
     * @return LogBroadcaster
     */
    protected function createLogDriver(array $config): LogBroadcaster
    {
        return new LogBroadcaster($config);
    }
}
