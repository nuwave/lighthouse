<?php

namespace Nuwave\Lighthouse\Subscriptions\Broadcasters;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

class PusherBroadcaster implements Broadcaster
{
    public const EVENT_NAME = 'lighthouse-subscription';

    /**
     * @var \Pusher\Pusher
     */
    protected $pusher;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions
     */
    protected $storage;

    /**
     * @param  \Pusher\Pusher  $pusher  TODO make into proper typehint
     */
    public function __construct($pusher)
    {
        $this->pusher = $pusher;
        $this->storage = app(StoresSubscriptions::class);
    }

    /**
     * Authorize subscription request.
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
        (new Collection($request->input('events', [])))
            ->filter(function (array $event): bool {
                return $event['name'] === 'channel_vacated';
            })
            ->each(function (array $event): void {
                $this->storage->deleteSubscriber($event['channel']);
            });

        return response()->json(['message' => 'okay']);
    }

    /**
     * Send data to subscriber.
     *
     * @param  mixed[]  $data
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
