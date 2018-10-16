<?php

namespace Nuwave\Lighthouse\Subscriptions\Storage;

use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\Models\Topic;
use Nuwave\Lighthouse\Subscriptions\Models\Subscription;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;

class MemoryStorage implements StoresSubscriptions
{
    /**
     * @var string[]
     */
    protected $topics = [];

    /**
     * @var Subscriber[]
     */
    protected $subscribers = [];

    /**
     * Find subscriber by channel.
     *
     * @param string $channel
     *
     * @return Subscriber|null
     */
    public function subscriberByChannel($channel)
    {
        return collect($this->subscribers)
            ->first(function (Subscriber $subscriber) use ($channel) {
                return $channel == $subscriber->channel;
            });
    }

    /**
     * Get collection of subscribers by topic.
     *
     * @param string $topic
     *
     * @return \Illuminate\Support\Collection
     */
    public function subscribersByTopic($topic)
    {
        $topic = array_get($this->topics, $topic, []);

        return collect($this->subscribers)
            ->filter(function (Subscriber $subscriber) use ($topic) {
                return in_array($subscriber->channel, $topic);
            });
    }

    /**
     * Store subscription.
     *
     * @param Subscriber $subscription
     * @param string     $topic
     */
    public function storeSubscriber(Subscriber $subscription, $topic)
    {
        $channels = array_get($this->topics, $topic, []);
        $channels[] = $subscription->channel;

        $this->topics[$topic] = $channels;
        $this->subscribers[] = $subscription;
    }

    /**
     * Delete subscriber.
     *
     * @param string $channel
     *
     * @return Subscriber|null
     */
    public function deleteSubscriber($channel)
    {
        $subscriber = $this->subscriberByChannel($channel);

        $this->topics = collect($this->topics)->map(function ($channels) use ($channel) {
            return array_filter($channels, function ($ch) use ($channel) {
                return $ch != $channel;
            });
        })->toArray();

        $this->subscribers = collect($this->subscribers)
            ->filter(function (Subscriber $subscriber) use ($channel) {
                return $channel != $subscriber->channel;
            });

        return $subscriber;
    }
}
