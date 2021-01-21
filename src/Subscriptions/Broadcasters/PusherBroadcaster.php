<?php

namespace Nuwave\Lighthouse\Subscriptions\Broadcasters;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Pusher\Pusher;

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

    public function __construct(Pusher $pusher)
    {
        $this->pusher = $pusher;
        $this->storage = app(StoresSubscriptions::class);
    }

    public function authorized(Request $request): JsonResponse
    {
        $channel = $request->input('channel_name');
        $socketId = $request->input('socket_id');
        $data = \Safe\json_decode(
            $this->pusher->socket_auth($channel, $socketId),
            true
        );

        return new JsonResponse($data, 200);
    }

    public function unauthorized(Request $request): JsonResponse
    {
        return new JsonResponse([
            'error' => 'unauthorized',
        ], 403);
    }

    public function hook(Request $request): JsonResponse
    {
        foreach ($request->input('events', []) as $event) {
            if ($event['name'] === 'channel_vacated') {
                $this->storage->deleteSubscriber($event['channel']);
            }
        }

        return new JsonResponse(['message' => 'okay']);
    }

    public function broadcast(Subscriber $subscriber, $data): void
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
