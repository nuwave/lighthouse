<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;

class SubscriptionController extends Controller
{
    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions
     */
    protected $broadcaster;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\BroadcastManager
     */
    protected $broadcasterManager;

    /**
     * @param  \Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions  $broadcaster
     * @param  \Nuwave\Lighthouse\Subscriptions\BroadcastManager  $broadcastManager
     * @return void
     */
    public function __construct(BroadcastsSubscriptions $broadcaster, BroadcastManager $broadcastManager)
    {
        $this->broadcaster = $broadcaster;
        $this->broadcasterManager = $broadcastManager;
    }

    /**
     * Authenticate subscriber.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function authorize(Request $request): Response
    {
        return $this->broadcaster->authorize($request);
    }

    /**
     * Handle pusher webhook.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function webhook(Request $request): Response
    {
        return $this->broadcasterManager->hook($request);
    }
}
