<?php

namespace Nuwave\Lighthouse\Subscriptions\Events;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Nuwave\Lighthouse\Subscriptions\Job\BroadcastSubscriptionJob;

class BroadcastSubscriptionListener
{
    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $dispatcher;

    public function __construct(BusDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Handle the event.
     *
     * @param \Nuwave\Lighthouse\Subscriptions\Events\BroadcastSubscriptionEvent $event
     */
    public function handle(BroadcastSubscriptionEvent $event): void
    {
        $this->dispatcher->dispatch(
            (new BroadcastSubscriptionJob($event))
                ->onQueue(config('lighthouse.subscriptions.broadcasts_queue_name', null))
        );
    }
}
