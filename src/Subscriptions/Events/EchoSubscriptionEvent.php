<?php

namespace Nuwave\Lighthouse\Subscriptions\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class EchoSubscriptionEvent implements ShouldBroadcast
{
    /**
     * @var string
     */
    public $channel;

    /**
     * @var mixed The data to broadcast.
     */
    public $data;

    /**
     * @param mixed $data The data to broadcast.
     */
    public function __construct(string $channel, $data)
    {
        $this->channel = $channel;
        $this->data = $data;
    }

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel($this->channel);
    }

    /**
     * Returns an event name.
     *
     * Allows the echo client to receive this event using .listen('.lighthouse.subscription', () => ...).
     */
    public function broadcastAs(): string
    {
        return 'lighthouse.subscription';
    }
}
