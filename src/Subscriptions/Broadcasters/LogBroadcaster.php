<?php

namespace Nuwave\Lighthouse\Subscriptions\Broadcasters;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;

class LogBroadcaster implements Broadcaster
{
    /**
     * The user-defined configuration options.
     *
     * @var mixed[]
     */
    protected $config = [];

    /**
     * A map from channel names to data.
     *
     * @var mixed
     */
    protected $broadcasts = [];

    /**
     * @param  array  $config
     * @return void
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Authorize subscription request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function authorized(Request $request): JsonResponse
    {
        return response()->json(['message' => 'ok'], 200);
    }

    /**
     * Handle unauthorized subscription request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unauthorized(Request $request): JsonResponse
    {
        return response()->json(['error' => 'unauthorized'], 403);
    }

    /**
     * Handle subscription web hook.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function hook(Request $request): JsonResponse
    {
        return response()->json(['message' => 'okay']);
    }

    /**
     * Send data to subscriber.
     *
     * @param  \Nuwave\Lighthouse\Subscriptions\Subscriber  $subscriber
     * @param  array  $data
     * @return void
     */
    public function broadcast(Subscriber $subscriber, array $data): void
    {
        $this->broadcasts[$subscriber->channel] = $data;
    }

    /**
     * Get the data that is being broadcast.
     *
     * @param  string|null  $key
     * @return array|null
     */
    public function broadcasts(?string $key = null): ?array
    {
        return Arr::get($this->broadcasts, $key);
    }

    /**
     * Get configuration options.
     *
     * @return mixed[]
     */
    public function config(): array
    {
        return $this->config;
    }
}
