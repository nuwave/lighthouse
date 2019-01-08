<?php

namespace Nuwave\Lighthouse\Subscriptions\Events;

use Illuminate\Queue\SerializesModels;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription as Subscription;

class BroadcastSubscriptionEvent
{
    use SerializesModels;

    /**
     * @var \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription
     */
    public $subscription;

    /**
     * @var string
     */
    public $fieldName;

    /**
     * @var mixed
     */
    public $root;

    /**
     * @param  \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription  $subscription
     * @param  string  $fieldName
     * @param  mixed  $root
     * @return void
     */
    public function __construct(Subscription $subscription, string $fieldName, $root)
    {
        $this->subscription = $subscription;
        $this->fieldName = $fieldName;
        $this->root = $root;
    }
}
