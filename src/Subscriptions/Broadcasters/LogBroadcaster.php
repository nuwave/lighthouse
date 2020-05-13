<?php

namespace Nuwave\Lighthouse\Subscriptions\Broadcasters;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

class LogBroadcaster implements Broadcaster
{
    /**
     * The user-defined configuration options.
     *
     * @var array<mixed>
     */
    protected $config = [];

    /**
     * A map from channel names to data.
     *
     * @var array<string, array<mixed>>
     */
    protected $broadcasts = [];

    /**
     * @param  array<mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Authorize subscription request.
     */
    public function authorized(Request $request): JsonResponse
    {
        return response()->json(['message' => 'ok'], 200);
    }

    /**
     * Handle unauthorized subscription request.
     */
    public function unauthorized(Request $request): JsonResponse
    {
        return response()->json(['error' => 'unauthorized'], 403);
    }

    /**
     * Handle subscription web hook.
     */
    public function hook(Request $request): JsonResponse
    {
        return response()->json(['message' => 'okay']);
    }

    /**
     * Send data to subscriber.
     */
    public function broadcast(Subscriber $subscriber, array $data): void
    {
        $this->broadcasts[$subscriber->channel] = $data;
    }

    /**
     * Get the data that is being broadcast.
     *
     * @return array<mixed>|null
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
