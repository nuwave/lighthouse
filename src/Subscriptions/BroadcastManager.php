<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Container\Container as Application;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;

/**
 * @method void                      broadcast(Subscriber $subscriber, array $data)
 * @method \Illuminate\Http\Response hook(\Illuminate\Http\Request $request)
 * @method \Illuminate\Http\Response authorized(\Illuminate\Http\Request $request)
 * @method \Illuminate\Http\Response unauthorized(\Illuminate\Http\Request $request)
 */
class BroadcastManager
{
    /**
     * The application instance.
     *
     * @var Application
     */
    protected $app;

    /**
     * The array of resolved broadcasters.
     *
     * @var array
     */
    protected $broadcasters = [];

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * Create a new broadcaster manager instance.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get a broadcaster driver instance.
     *
     * @param string|null $driver
     *
     * @return mixed
     */
    public function driver($driver = null)
    {
        return $this->broadcaster($driver);
    }

    /**
     * Attempt to get the broadcaster from the local cache.
     *
     * @param string $name
     *
     * @return BroadcastsSubscriptions
     */
    protected function get(string $name)
    {
        return $this->broadcasters[$name] ?? $this->resolve($name);
    }

    /**
     * Get a broadcaster instance by name.
     *
     * @param string|null $name
     *
     * @return BroadcastsSubscriptions
     */
    public function broadcaster($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->broadcasters[$name] = $this->get($name);
    }

    /**
     * Get the default broadcast driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['lighthouse.subscriptions.driver'];
    }

    /**
     * Set the default broadcast driver name.
     *
     * @param string $name
     */
    public function setDefaultDriver($name)
    {
        $this->app['config']['lighthouse.subscriptions.driver'] = $name;
    }

    /**
     * Get the subscription configuration.
     *
     * @return array
     */
    protected function getConfig()
    {
        return $this->app['config']['lighthouse.subscriptions'];
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param string   $driver
     * @param \Closure $callback
     *
     * @return self
     */
    public function extend($driver, \Closure $callback)
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Resolve the given broadcaster.
     *
     * @param string $name
     *
     * @throws \InvalidArgumentException
     *
     * @return BroadcastsSubscriptions
     */
    protected function resolve($name)
    {
        $config = $this->getConfig();

        if (isset($this->customCreators[$name])) {
            return $this->callCustomCreator($config);
        }

        $driverMethod = 'create'.ucfirst($name).'Driver';

        if (! method_exists($this, $driverMethod)) {
            throw new \InvalidArgumentException("Subscription driver [{$name}] is not supported.");
        }

        return $this->{$driverMethod}($config);
    }

    /**
     * Call a custom driver creator.
     *
     * @param array $config
     *
     * @return mixed
     */
    protected function callCustomCreator(array $config)
    {
        return $this->customCreators[$config['driver']]($this->app, $config);
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
        $appCluster = config('broadcasting.pusher.options.cluster');

        $pusher = new \Pusher\Pusher($appKey, $appSecret, $appId, [
            'cluster' => $appCluster,
        ]);

        return new PusherBroadcaster($pusher);
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}
