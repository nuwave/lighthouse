<?php declare(strict_types=1);

namespace Tests;

use Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider;

trait TestsSubscriptions
{
    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [
                SubscriptionServiceProvider::class,
            ],
        );
    }
}
