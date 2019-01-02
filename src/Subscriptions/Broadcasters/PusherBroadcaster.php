<?php

namespace Nuwave\Lighthouse\Subscriptions\Broadcasters;

use Pusher\Pusher;
use Illuminate\Support\Arr;
use Pusher\PusherException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;

class PusherBroadcaster implements Broadcaster
{
    const EVENT_NAME = 'lighthouse-subscription';

    /**
     * @var Pusher
     */
    protected $pusher;

    /**
     * @var StoresSubscriptions
     */
    protected $storage;

    /**
     * Create instance of pusher broadcaster.
     *
     * @param Pusher $pusher
     */
    public function __construct($pusher)
    {
        $this->pusher = $pusher;
        $this->storage = app(StoresSubscriptions::class);
    }

    /**
     * Authorize subscription request.
     *
     * @param Request $request
     *
     * @throws PusherException
     *
     * @return JsonResponse
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
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function unauthorized(Request $request): JsonResponse
    {
        return response()->json(['error' => 'unauthorized'], 403);
    }

    /**
     * Handle subscription web hook.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function hook(Request $request): JsonResponse
    {
        collect($request->input('events', []))
            ->filter(function ($event) {
                return 'channel_vacated' == array_get($event, 'name');
            })->each(function (array $event): void {
                $this->storage->deleteSubscriber(
                    Arr::get($event, 'channel')
                );
            });

        return response()->json(['message' => 'okay']);
    }

    /**
     * Send data to subscriber.
     *
     * @param Subscriber $subscriber
     * @param mixed[] $data
     *
     * @throws PusherException
     *
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
