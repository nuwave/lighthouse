<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController
{
    public function authorize(Request $request, BroadcastsSubscriptions $broadcaster): Response
    {
        return $broadcaster->authorize($request);
    }

    public function webhook(Request $request, BroadcastManager $broadcastManager): Response
    {
        return $broadcastManager->hook($request);
    }
}
