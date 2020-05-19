<?php

namespace Nuwave\Lighthouse\Subscriptions\Broadcasters;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Events\EchoSubscriptionEvent;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Symfony\Component\HttpFoundation\Response;

class RedisBroadcaster implements Broadcaster
{
    /**
     * @var BroadcastManager
     */
    private $broadcaster;

    public function __construct(BroadcastManager $broadcaster)
    {
        $this->broadcaster = $broadcaster;
    }

    /**
     * @param Subscriber $subscriber
     * @param array $data
     */
    public function broadcast(Subscriber $subscriber, array $data)
    {
        $this->broadcaster->event(
            new EchoSubscriptionEvent($subscriber->channel, Arr::get($data, 'data', $data))
        );
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function authorized(Request $request)
    {
        $userId = md5($request->input('channel_name') . $request->input('socket_id'));

        return new JsonResponse([
            'channel_data' => [
                'user_id' => $userId,
                'user_info' => [],
            ]
        ]);
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function unauthorized(Request $request)
    {
        return new JsonResponse(['message' => 'Unauthorized'], 403);
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function hook(Request $request)
    {
        // Does nothing.
        // The redis broadcaster has the lighthouse:subscribe command to take care of cleaning vacant channels.

        return new JsonResponse(['message' => 'okay'], 200);
    }
}
