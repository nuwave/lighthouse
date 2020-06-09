<?php

namespace Nuwave\Lighthouse\Subscriptions\Events;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Nuwave\Lighthouse\Subscriptions\BroadcastSubscriptionJob;

/**
 * @deprecated This class is just here to preserve backwards compatiblity with v4.
 * TODO remove the event and handle subscriptions as commands only in v5
 */
class BroadcastSubscriptionListener
{
    /**
     * @var \Illuminate\Contracts\Bus\Dispatcher
     */
    protected $busDispatcher;

    public function __construct(BusDispatcher $busDispatcher)
    {
        $this->busDispatcher = $busDispatcher;
    }

    /**
     * Handle the event.
     *
     * @param  \Nuwave\Lighthouse\Subscriptions\Events\BroadcastSubscriptionEvent  $event
     */
    public function handle(BroadcastSubscriptionEvent $event): void
    {
        $this->busDispatcher->dispatch(
            (new BroadcastSubscriptionJob(
                $event->subscription,
                $event->fieldName,
                $event->root
            ))->onQueue(config('lighthouse.subscriptions.broadcasts_queue_name', null))
        );
    }
}
