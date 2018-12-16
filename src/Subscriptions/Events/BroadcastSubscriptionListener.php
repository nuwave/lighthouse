<?php

namespace Nuwave\Lighthouse\Subscriptions\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;

class BroadcastSubscriptionListener implements ShouldQueue
{
    /**
     * @var BroadcastsSubscriptions
     */
    protected $broadcaster;

    /**
     * @param BroadcastsSubscriptions $broadcaster
     */
    public function __construct(BroadcastsSubscriptions $broadcaster)
    {
        $this->broadcaster = $broadcaster;
    }

    /**
     * Handle the event.
     *
     * @param BroadcastSubscriptionEvent $event
     */
    public function handle(BroadcastSubscriptionEvent $event)
    {
        $this->broadcaster->broadcast(
            $event->subscription,
            $event->fieldName,
            $event->root
        );
    }
}
