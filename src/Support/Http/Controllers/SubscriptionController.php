<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nuwave\Lighthouse\Schema\Subscriptions\Contracts\StoresSubscriptions as Storage;
use Nuwave\Lighthouse\Schema\Subscriptions\Contracts\BroadcastsSubscriptions as Broadcaster;

class GraphQLController extends Controller
{
    /**
     * @var Storage
     */
    protected $storage;

    /**
     * @var Broadcaster
     */
    protected $broadcaster;

    /**
     * @param Storage     $storage
     * @param Broadcaster $broadcaster
     */
    public function __construct(Storage $storage, Broadcaster $broadcaster)
    {
        $this->storage = $storage;
        $this->broadcaster = $broadcaster;
    }

    /**
     * Handle pusher webook.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function webhook(Request $request)
    {
        collect($request->input('events', []))
            ->filter(function ($event) {
                return 'channel_vacated' == array_get($event, 'name');
            })->each(function ($event) {
                $this->storage->deleteSubscriber(array_get($event, 'channel'));
            });

        return response()->json(['message' => 'okay']);
    }

    /**
     * Authenticate subscriber.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function subscription(Request $request)
    {
        $data = $this->broadcaster->authorize(
            $request->input('channel_name'),
            $request->input('socket_id'),
            $request
        );

        $status = isset($data['error']) ? 403 : 200;

        return response()->json($data, $status);
    }
}
