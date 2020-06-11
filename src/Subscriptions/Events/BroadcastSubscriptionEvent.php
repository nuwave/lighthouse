<?php

namespace Nuwave\Lighthouse\Subscriptions\Events;

use Illuminate\Queue\SerializesModels;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

/**
 * @deprecated will be removed in v5 and replaced with a Job
 */
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

    public $root; // @phpstan-ignore-line

    public function __construct(GraphQLSubscription $subscription, string $fieldName, $root)
    {
        $this->subscription = $subscription;
        $this->fieldName = $fieldName;
        $this->root = $root;
    }
}
