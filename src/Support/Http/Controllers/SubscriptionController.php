<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
     * @param BroadcastsSubscriptions $broadcaster
     * @param BroadcastManager        $broadcastManager
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
     * @return Response
     */
    public function authorize(Request $request): Response
    {
        return $this->broadcaster->authorize($request);
    }

    /**
     * Handle pusher webhook.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function webhook(Request $request): Response
    {
        return $this->broadcasterManager->hook($request);
    }
}
