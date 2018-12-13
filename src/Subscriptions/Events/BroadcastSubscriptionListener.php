<?php

namespace Nuwave\Lighthouse\Subscriptions\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions as Broadcaster;

class BroadcastSubscriptionListener implements ShouldQueue
{
    /**
     * @var Broadcaster
     */
    protected $broadcaster;

    /**
     * @param Broadcaster $broadcaster
     */
    public function __construct(Broadcaster $broadcaster)
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
