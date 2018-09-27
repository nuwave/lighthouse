<?php

namespace Nuwave\Lighthouse\Subscriptions\Storage;

use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\Models\Topic;
use Nuwave\Lighthouse\Subscriptions\Models\Subscription;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;

class DatabaseStorage implements StoresSubscriptions
{
    /**
     * Find subscriber by channel.
     *
     * @param string $channel
     *
     * @return Subscription
     */
    public function subscriberByChannel($channel)
    {
        $subscription = Subscription::where('channel', $channel)->firstOrFail();

        return $subscription->toSubscriber();
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
        $topic = Topic::with(['subscriptions'])->where('key', $topic)->first();

        if (! $topic) {
            return collect([]);
        }

        return $topic->subscriptions->map(function (Subscription $subscription) {
            return $subscription->toSubscriber();
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
        $topic = $this->topic($topic);

        $topic->subscriptions()->save(
            $this->subscription($subscription)
        );
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
        if ($subscription = Subscription::where('channel', $channel)->first()) {
            $subscriber = $subscription->toSubscriber();
            $subscription->delete();

            return $subscriber;
        }

        return null;
    }

    /**
     * Get topic by key.
     *
     * @param string $key
     *
     * @return Topic
     */
    protected function topic($key)
    {
        return Topic::firstOrCreate(['key' => $key]);
    }

    /**
     * @param Subscriber $subscriber
     *
     * @return Subscription
     */
    protected function subscription(Subscriber $subscriber)
    {
        return new Subscription($subscriber->toArray());
    }
}
