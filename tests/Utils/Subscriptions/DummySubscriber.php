<?php declare(strict_types=1);

namespace Tests\Utils\Subscriptions;

use Nuwave\Lighthouse\Subscriptions\Subscriber;

final class DummySubscriber extends Subscriber
{
    public function __construct(string $channel, string $topic)
    {
        $this->channel = $channel;
        $this->topic = $topic;
    }

    public function __serialize(): array
    {
        return [
            'channel' => $this->channel,
            'topic' => $this->topic,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->channel = $data['channel'];
        $this->topic = $data['topic'];
    }
}
