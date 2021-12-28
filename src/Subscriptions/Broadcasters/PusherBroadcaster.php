<?php

namespace Nuwave\Lighthouse\Subscriptions\Broadcasters;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Pusher\ApiErrorException;
use Pusher\Pusher;

class PusherBroadcaster implements Broadcaster
{
    /**
     * @var \Pusher\Pusher
     */
    protected $pusher;

    /**
     * @var \Illuminate\Contracts\Debug\ExceptionHandler
     */
    protected $exceptionHandler;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions
     */
    protected $storage;

    public function __construct(Pusher $pusher, ExceptionHandler $exceptionHandler)
    {
        $this->pusher = $pusher;
        $this->exceptionHandler = $exceptionHandler;
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
            if ('channel_vacated' === $event['name']) {
                $this->storage->deleteSubscriber($event['channel']);
            }
        }

        return new JsonResponse(['message' => 'okay']);
    }

    public function broadcast(Subscriber $subscriber, $data): void
    {
        try {
            $this->pusher->trigger(
                $subscriber->channel,
                self::EVENT_NAME,
                [
                    'more' => true,
                    'result' => $data,
                ]
            );
        } catch (ApiErrorException $e) {
            $this->exceptionHandler->report($e);
        }
    }
}
