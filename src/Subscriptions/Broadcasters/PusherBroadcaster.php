<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions\Broadcasters;

use Illuminate\Container\Container;
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
    protected StoresSubscriptions $storage;

    public function __construct(
        protected Pusher $pusher,
        protected ExceptionHandler $exceptionHandler,
    ) {
        $this->storage = Container::getInstance()->make(StoresSubscriptions::class);
    }

    public function authorized(Request $request): JsonResponse
    {
        $channel = $request->input('channel_name');
        $socketId = $request->input('socket_id');
        $data = \Safe\json_decode(
            $this->pusher->socket_auth($channel, $socketId),
            true,
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

    public function broadcast(Subscriber $subscriber, mixed $data): void
    {
        try {
            $this->pusher->trigger(
                $subscriber->channel,
                self::EVENT_NAME,
                [
                    'more' => true,
                    'result' => $data,
                ],
            );
        } catch (ApiErrorException $apiErrorException) {
            $this->exceptionHandler->report($apiErrorException);
        }
    }
}
