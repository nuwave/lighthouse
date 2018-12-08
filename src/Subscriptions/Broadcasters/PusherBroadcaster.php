<?php

namespace Nuwave\Lighthouse\Subscriptions\Broadcasters;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions as Storage;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions as Authorizer;

class PusherBroadcaster implements Broadcaster
{
    const EVENT_NAME = 'lighthouse-subscription';

    /**
     * Undocumented variable.
     *
     * @var \Pusher\Pusher
     */
    protected $pusher;

    /**
     * Create instance of pusher broadcaster.
     *
     * @param \Pusher\Pusher $pusher
     */
    public function __construct($pusher)
    {
        $this->pusher = $pusher;
    }

    /**
     * Authorize subscription request.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function authorized(Request $request): Response
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
     * @return \Illuminate\Http\Response
     */
    public function unauthorized(Request $request)
    {
        $this->storage()->deleteSubscriber(
            $request->input('channel_name')
        );

        return response()->json(['error' => 'unauthorized'], 403);
    }

    /**
     * Handle subscription web hook.
     *
     * @param Request $request
     *
     * @return \Illuminate\Support\Response
     */
    public function hook(Request $request): Response
    {
        collect($request->input('events', []))
            ->filter(function ($event) {
                return 'channel_vacated' == array_get($event, 'name');
            })->each(function ($event) {
                $this->storage()->deleteSubscriber(
                    array_get($event, 'channel')
                );
            });

        return response()->json(['message' => 'okay']);
    }

    /**
     * Send data to subscriber.
     *
     * @param Subscriber $subscriber
     * @param array      $data
     */
    public function broadcast(Subscriber $subscriber, array $data)
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

    /**
     * Get instance of subscription storage.
     *
     * @return Storage
     */
    protected function storage(): Storage
    {
        return app(Storage::class);
    }
}
