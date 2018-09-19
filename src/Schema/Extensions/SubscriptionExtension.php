<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Nuwave\Lighthouse\Schema\Extensions\GraphQLExtension;
use Nuwave\Lighthouse\Schema\Subscriptions\SubscriptionRegistry;

class SubscriptionExtension extends GraphQLExtension
{
    /**
     * @var SubscriptionRegistry
     */
    protected $registry;

    /**
     * @param SubscriptionRegistry $registry
     */
    public function __construct(SubscriptionRegistry $registry)
    {
        $this->registry = $registry;
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
     * Format extension output.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => 1,
            'channels' => $this->registry->toArray(),
        ];
    }
}
