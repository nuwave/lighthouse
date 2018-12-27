<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Nuwave\Lighthouse\Support\DriverManager;
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
    protected function configKey()
    {
        return 'lighthouse.subscriptions.broadcasters';
    }

    /**
     * Get configuration driver key.
     *
     * @return string
     */
    protected function driverKey()
    {
        return 'lighthouse.subscriptions.broadcaster';
    }

    /**
     * The interface the driver should implement.
     *
     * @return string
     */
    protected function interface()
    {
        return 'Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster';
    }

    /**
     * Create instance of pusher driver.
     *
     * @param array $config
     *
     * @return Broadcaster
     */
    protected function createPusherDriver(array $config)
    {
        $appKey = config('broadcasting.connections.pusher.key');
        $appSecret = config('broadcasting.connections.pusher.secret');
        $appId = config('broadcasting.connections.pusher.app_id');
        $options = config('broadcasting.connections.pusher.options', []);

        $pusher = new \Pusher\Pusher($appKey, $appSecret, $appId, $options);

        return new PusherBroadcaster($pusher);
    }

    /**
     * Create instance of log driver.
     *
     * @param array $config
     *
     * @return LogBroadcaster
     */
    protected function createLogDriver(array $config)
    {
        return new LogBroadcaster($config);
    }
}
