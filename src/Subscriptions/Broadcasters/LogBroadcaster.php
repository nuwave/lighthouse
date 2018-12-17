<?php

namespace Nuwave\Lighthouse\Subscriptions\Broadcasters;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;

class LogBroadcaster implements Broadcaster
{
    /** @var array */
    protected $config = [];

    /** @var array */
    protected $broadcasts = [];

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Authorize subscription request.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function authorized(Request $request): Response
    {
        return response()->json(['message' => 'ok'], 200);
    }

    /**
     * Handle unauthorized subscription request.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function unauthorized(Request $request): Response
    {
        return response()->json(['error' => 'unauthorized'], 403);
    }

    /**
     * Handle subscription web hook.
     *
     * @param Request $request
     *
     * @return \Illuminate\Support\Response
     */
    public function hook(Request $request): Response
    {
        return response()->json(['message' => 'okay']);
    }

    /**
     * Send data to subscriber.
     *
     * @param Subscriber $subscriber
     * @param array      $data
     */
    public function broadcast(Subscriber $subscriber, array $data)
    {
        $this->broadcasts[$subscriber->channel] = $data;
    }

    /**
     * Get broadcasted data.
     *
     * @param string|null $key
     *
     * @return array|null
     */
    public function broadcasts($key = null)
    {
        return $key ? Arr::get($this->broadcasts, $key) : $this->broadcasts;
    }

    /**
     * Get configuration options.
     *
     * @return array
     */
    public function config(): array
    {
        return $this->config;
    }
}
