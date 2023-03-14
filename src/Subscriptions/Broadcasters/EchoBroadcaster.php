<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions\Broadcasters;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Events\EchoSubscriptionEvent;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

class EchoBroadcaster implements Broadcaster
{
    public function __construct(
        protected BroadcastManager $broadcaster,
    ) {}

    public function broadcast(Subscriber $subscriber, mixed $data): void
    {
        $this->broadcaster->event(
            new EchoSubscriptionEvent($subscriber->channel, $data),
        );
    }

    public function authorized(Request $request): JsonResponse
    {
        $userId = md5(
            $request->input('channel_name')
            . $request->input('socket_id'),
        );

        return new JsonResponse([
            'channel_data' => [
                'user_id' => $userId,
                'user_info' => [],
            ],
        ], 200);
    }

    public function unauthorized(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Unauthorized',
        ], 403);
    }

    public function hook(Request $request): JsonResponse
    {
        // Does nothing.
        // The redis broadcaster has the lighthouse:subscribe command to take care of cleaning vacant channels.

        return new JsonResponse([
            'message' => 'okay',
        ], 200);
    }
}
