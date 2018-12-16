<?php

namespace Nuwave\Lighthouse\Subscriptions\Broadcasters;

use Pusher\Pusher;
use Pusher\PusherException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;

class PusherBroadcaster implements Broadcaster
{
    const EVENT_NAME = 'lighthouse-subscription';

    /** @var Pusher */
    protected $pusher;

    /** @var StoresSubscriptions */
    protected $storage;

    /**
     * Create instance of pusher broadcaster.
     *
     * @param Pusher $pusher
     */
    public function __construct(Pusher $pusher)
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
     * @return Response
     */
    public function authorized(Request $request)
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
     * @return Response
     */
    public function unauthorized(Request $request)
    {
        return response()->json(['error' => 'unauthorized'], 403);
    }

    /**
     * Handle subscription web hook.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function hook(Request $request)
    {
        collect($request->input('events', []))
            ->filter(function ($event) {
                return 'channel_vacated' == array_get($event, 'name');
            })->each(function ($event) {
                $this->storage->deleteSubscriber(
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
     *
     * @throws PusherException
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
     * Register subscription routes.
     *
     * @param \Illuminate\Routing\Router|\Laravel\Lumen\Routing\Router $router
     */
    public static function routes($router)
    {
        $router->post('graphql/subscriptions/auth', [
            'as' => 'lighthouse.subscriptions.auth',
            'uses' => 'Nuwave\Lighthouse\Support\Http\Controllers\SubscriptionController@authorize',
        ]);

        $router->post('graphql/subscriptions/webhook', [
            'as' => 'lighthouse.subscriptions.auth',
            'uses' => 'Nuwave\Lighthouse\Support\Http\Controllers\SubscriptionController@webhook',
        ]);
    }
}
