<?php

namespace Nuwave\Lighthouse\Schema\Fields;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Context;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

class NotFoundSubscriptionField extends SubscriptionField
{
    /**
     * Authorize subscriber request.
     *
     * @param Subscriber $subscriber
     * @param Request    $request
     *
     * @return bool
     */
    public function authorize(Subscriber $subscriber, Request $request)
    {
        return false;
    }

    /**
     * Filter subscribers who should receive subscription.
     *
     * @param Subscriber $subscriber
     * @param mixed      $root
     *
     * @return bool
     */
    public function filter(Subscriber $subscriber, $root)
    {
        return false;
    }

    /**
     * Resolve the subscription.
     *
     * @param mixed         $root
     * @param array         $args
     * @param Context|mixed $context
     * @param ResolveInfo   $resolveInfo
     *
     * @return mixed
     */
    public function resolve($root, array $args, $context, ResolveInfo $resolveInfo)
    {
        return null;
    }
}
