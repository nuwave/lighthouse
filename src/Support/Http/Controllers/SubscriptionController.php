<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;

class SubscriptionController extends Controller
{
    /**
     * @var BroadcastManager
     */
    protected $broadcaster;

    /**
     * @param BroadcastManager $broadcaster
     */
    public function __construct(Broadcaster $broadcaster)
    {
        $this->broadcaster = $broadcaster;
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
        return $this->broadcaster->hook($request);
    }
}
