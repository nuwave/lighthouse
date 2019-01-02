<?php

namespace Tests\Utils\Directives;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

class FooSubscription extends GraphQLSubscription
{
    /**
     * Authorize subscriber request.
     *
     * @param Subscriber $subscriber
     * @param Request    $request
     *
     * @return bool
     */
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        return true;
    }

    /**
     * Filter subscribers who should receive subscription.
     *
     * @param Subscriber $subscriber
     * @param mixed      $root
     *
     * @return bool
     */
    public function filter(Subscriber $subscriber, $root): bool
    {
        return true;
    }
}
