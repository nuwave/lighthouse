<?php

namespace Nuwave\Lighthouse\Subscriptions\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;

class EchoSubscriptionEvent implements ShouldBroadcastNow
{
    /**
     * @var string
     */
    public $channel;

    /**
     * @var mixed the data to broadcast
     */
    public $data;

    /**
     * @param  mixed  $data  the data to broadcast
     */
    public function __construct(string $channel, $data)
    {
        $this->channel = $channel;
        $this->data = $data;
    }

    public function broadcastOn(): Channel
    {
        return new Channel($this->channel);
    }

    /**
     * Returns an event name.
     *
     * Allows the echo client to receive this event using .listen('.lighthouse.subscription', () => ...).
     */
    public function broadcastAs(): string
    {
        return Broadcaster::EVENT_NAME;
    }
}
