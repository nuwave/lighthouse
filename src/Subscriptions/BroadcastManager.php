<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Nuwave\Lighthouse\Support\DriverManager;
use Illuminate\Container\Container as Application;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;

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
     * Create instance of pusher driver.
     *
     * @param array $config
     *
     * @return Broadcaster
     */
    protected function createPusherDriver(array $config)
    {
        $appKey = config('broadcasting.pusher.key');
        $appSecret = config('broadcasting.pusher.secret');
        $appId = config('broadcasting.pusher.app_id');
        $options = config('broadcasting.pusher.options', []);

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
