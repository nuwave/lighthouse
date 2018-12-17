<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;

class SubscriptionController extends Controller
{
    /**
     * @var BroadcastsSubscriptions
     */
    protected $broadcaster;

    /**
     * @var BroadcastManager
     */
    protected $broadcasterManager;

    /**
     * @param BroadcastManager $broadcaster
     */
    public function __construct(BroadcastsSubscriptions $broadcaster, BroadcastManager $broadcastManager)
    {
        $this->broadcaster = $broadcaster;
        $this->broadcasterManager = $broadcastManager;
    }

    /**
     * Authenticate subscriber.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function authorize(Request $request)
    {
        return $this->broadcaster->authorize($request);
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
        return $this->broadcasterManager->hook($request);
    }
}
