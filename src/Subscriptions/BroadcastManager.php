<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Contracts\Debug\ExceptionHandler as LaravelExceptionHandler;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\EchoBroadcaster;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\PusherBroadcaster;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Support\DriverManager;
use Psr\Log\LoggerInterface;
use Pusher\Pusher;
use RuntimeException;

/**
 * @method void broadcast(\Nuwave\Lighthouse\Subscriptions\Subscriber $subscriber, array $data)
 * @method \Symfony\Component\HttpFoundation\Response hook(\Illuminate\Http\Request $request)
 * @method \Symfony\Component\HttpFoundation\Response authorized(\Illuminate\Http\Request $request)
 * @method \Symfony\Component\HttpFoundation\Response unauthorized(\Illuminate\Http\Request $request)
 */
class BroadcastManager extends DriverManager
{
    protected function configKey(): string
    {
        return 'lighthouse.subscriptions.broadcasters';
    }

    protected function driverKey(): string
    {
        return 'lighthouse.subscriptions.broadcaster';
    }

    protected function interface(): string
    {
        return Broadcaster::class;
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws \RuntimeException
     */
    protected function createPusherDriver(array $config): PusherBroadcaster
    {
        $connection = $config['connection'] ?? 'pusher';
        $driverConfig = config("broadcasting.connections.{$connection}");

        if (empty($driverConfig) || 'pusher' !== $driverConfig['driver']) {
            throw new RuntimeException("Could not initialize Pusher broadcast driver for connection: {$connection}.");
        }

        $pusher = new Pusher(
            $driverConfig['key'],
            $driverConfig['secret'],
            $driverConfig['app_id'],
            $driverConfig['options'] ?? []
        );

        if ($driverConfig['log'] ?? false) {
            $pusher->setLogger($this->app->make(LoggerInterface::class));
        }

        return new PusherBroadcaster($pusher, $this->app->make(LaravelExceptionHandler::class));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createLogDriver(array $config): LogBroadcaster
    {
        return new LogBroadcaster($config);
    }

    protected function createEchoDriver(): EchoBroadcaster
    {
        return $this->app->make(EchoBroadcaster::class);
    }
}
