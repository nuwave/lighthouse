<?php

namespace Nuwave\Lighthouse\Subscriptions\Broadcasters;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;

class PusherBroadcaster implements Broadcaster
{
    const EVENT_NAME = 'lighthouse-subscription';

    /**
     * @var \Pusher\Pusher
     */
    protected $pusher;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions
     */
    protected $storage;

    /**
     * Create instance of pusher broadcaster.
     *
     * @param  \Pusher\Pusher  $pusher
     * @return void
     */
    public function __construct($pusher)
    {
        $this->pusher = $pusher;
        $this->storage = app(StoresSubscriptions::class);
    }

    /**
     * Authorize subscription request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function authorized(Request $request): JsonResponse
    {
        $channel = $request->input('channel_name');
        $socketId = $request->input('socket_id');
        $data = json_decode(
            $this->pusher->socket_auth($channel, $socketId),
            true
        );

        return response()->json($data, 200);
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
        (new Collection($request->input('events', [])))
            ->filter(function ($event): bool {
                return Arr::get($event, 'name') === 'channel_vacated';
            })
            ->each(function (array $event): void {
                $this->storage->deleteSubscriber(
                    Arr::get($event, 'channel')
                );
            });

        return response()->json(['message' => 'okay']);
    }

    /**
     * Send data to subscriber.
     *
     * @param  \Nuwave\Lighthouse\Subscriptions\Subscriber  $subscriber
     * @param  mixed[]  $data
     * @return void
     */
    public function broadcast(Subscriber $subscriber, array $data): void
    {
        $this->pusher->trigger(
            $subscriber->channel,
            self::EVENT_NAME,
            [
                'more' => true,
                'result' => $data,
            ]
        );
    }
}
