<?php

namespace Nuwave\Lighthouse\Subscriptions\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Nuwave\Lighthouse\Subscriptions\Events\BroadcastSubscriptionEvent;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;

class BroadcastSubscriptionJob implements ShouldQueue
{
    use Queueable;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Events\BroadcastSubscriptionEvent
     */
    public $event;

    public function __construct(BroadcastSubscriptionEvent $event)
    {
        $this->event = $event;
    }

    /**
     * Handle the event.
     *
     * @param \Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions $broadcaster
     */
    public function handle(BroadcastsSubscriptions $broadcaster): void
    {
        $broadcaster->broadcast(
            $this->event->subscription,
            $this->event->fieldName,
            $this->event->root
        );
    }
}
