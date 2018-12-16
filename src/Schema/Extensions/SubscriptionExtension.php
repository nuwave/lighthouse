<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Nuwave\Lighthouse\Execution\GraphQLRequest;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;

class SubscriptionExtension extends GraphQLExtension
{
    /**
     * @var SubscriptionRegistry
     */
    protected $subscriptionRegistry;

    /**
     * @param SubscriptionRegistry $subscriptionRegistry
     */
    public function __construct(SubscriptionRegistry $subscriptionRegistry)
    {
        $this->subscriptionRegistry = $subscriptionRegistry;
    }

    /**
     * Extension name.
     *
     * @return string
     */
    public static function name(): string
    {
        return 'lighthouse_subscriptions';
    }

    /**
     * Handle request start.
     *
     * @param GraphQLRequest $request
     */
    public function start(GraphQLRequest $request)
    {
        $this->subscriptionRegistry->resetSubscribers();
    }

    /**
     * Format extension output.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => 1,
            'channels' => $this->subscriptionRegistry->subscribers(),
        ];
    }
}
