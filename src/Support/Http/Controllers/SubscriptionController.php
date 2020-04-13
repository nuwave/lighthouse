<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Symfony\Component\HttpFoundation\Response;

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

    public function __construct(BroadcastsSubscriptions $broadcaster, BroadcastManager $broadcastManager)
    {
        $this->broadcaster = $broadcaster;
        $this->broadcasterManager = $broadcastManager;
    }

    /**
     * Authenticate subscriber.
     */
    public function authorize(Request $request): Response
    {
        return $this->broadcaster->authorize($request);
    }

    /**
     * Handle pusher webhook.
     */
    public function webhook(Request $request): Response
    {
        return $this->broadcasterManager->hook($request);
    }
}
