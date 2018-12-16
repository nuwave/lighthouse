<?php

namespace Nuwave\Lighthouse\Subscriptions\Events;

use Illuminate\Queue\SerializesModels;
use Nuwave\Lighthouse\Schema\Fields\SubscriptionField;

class BroadcastSubscriptionEvent
{
    use SerializesModels;

    /**
     * @var SubscriptionField
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
     * @param SubscriptionField $subscription
     * @param string            $fieldName
     * @param mixed             $root
     */
    public function __construct(SubscriptionField $subscription, string $fieldName, $root)
    {
        $this->subscription = $subscription;
        $this->fieldName = $fieldName;
        $this->root = $root;
    }
}
