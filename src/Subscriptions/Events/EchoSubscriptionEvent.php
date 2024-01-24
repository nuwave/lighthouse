<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;

class EchoSubscriptionEvent implements ShouldBroadcastNow
{
    public function __construct(
        public string $channel,
        public mixed $data,
    ) {}

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
