<?php

namespace Nuwave\Lighthouse\Subscriptions\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;

class BroadcastSubscriptionJob implements ShouldQueue
{
    use Queueable;

    /**
     * The subscription field that was requested.
     *
     * @var \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription
     */
    public $subscription;

    /**
     * The name of the field.
     *
     * @var string
     */
    public $fieldName;

    /**
     * The root element to be passed when resolving the subscription.
     *
     * @var mixed
     */
    public $root;

    public function __construct(GraphQLSubscription $subscription, string $fieldName, $root)
    {
        $this->subscription = $subscription;
        $this->fieldName = $fieldName;
        $this->root = $root;
    }

    public function handle(BroadcastsSubscriptions $broadcaster): void
    {
        $broadcaster->broadcast(
            $this->subscription,
            $this->fieldName,
            $this->root
        );
    }
}
