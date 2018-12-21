<?php

namespace Nuwave\Lighthouse\Schema\Types;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use GraphQL\Type\Definition\ResolveInfo;

class NotFoundSubscription extends GraphQLSubscription
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
     * @param ResolveInfo   $info
     *
     * @return mixed
     */
    public function resolve($root, array $args, $context, ResolveInfo $info)
    {
        return null;
    }
}
