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
     * @var array<string, mixed>
     */
    protected $config = [];

    /**
     * A map from channel names to data.
     *
     * @var array<string, mixed>
     */
    protected $broadcasts = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function authorized(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'ok',
        ], 200);
    }

    public function unauthorized(Request $request): JsonResponse
    {
        return new JsonResponse([
            'error' => 'unauthorized',
        ], 403);
    }

    public function hook(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'okay',
        ], 200);
    }

    public function broadcast(Subscriber $subscriber, $data): void
    {
        $this->broadcasts[$subscriber->channel] = $data;
    }

    /**
     * @return mixed The data that is being broadcast
     */
    public function broadcasts(?string $key = null)
    {
        return Arr::get($this->broadcasts, $key);
    }

    /**
     * Get configuration options.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }
}
