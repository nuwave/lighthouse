<?php

namespace Tests\Utils\Subscriptions;

use Nuwave\Lighthouse\Subscriptions\Subscriber;

class DummySubscriber extends Subscriber
{
    public function __construct(string $channel, string $topic)
    {
        $this->channel = $channel;
        $this->topic = $topic;
    }

    public function serialize(): string
    {
        return \Safe\json_encode([
            'channel' => $this->channel,
            'topic' => $this->topic,
        ]);
    }

    /**
     * @param  string  $subscription
     */
    public function unserialize($subscription): void
    {
        $data = \Safe\json_decode($subscription);

        $this->channel = $data->channel;
        $this->topic = $data->topic;
    }
}
