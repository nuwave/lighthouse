<?php

namespace Nuwave\Lighthouse\Schema\Fields;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Context;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

abstract class SubscriptionField
{
    /**
     * Authorize subscriber request.
     *
     * @param Subscriber $subscriber
     * @param Request    $request
     *
     * @return bool
     */
    abstract public function authorize(Subscriber $subscriber, Request $request);

    /**
     * Filter subscribers who should receive subscription.
     *
     * @param Subscriber $subscriber
     * @param mixed      $root
     *
     * @return bool
     */
    abstract public function filter(Subscriber $subscriber, $root);

    /**
     * Resolve the subscription.
     *
     * @param mixed       $root
     * @param array       $args
     * @param Context     $context
     * @param ResolveInfo $resolveInfo
     *
     * @return mixed
     */
    public function resolve($root, array $args, $context, ResolveInfo $resolveInfo)
    {
        return $root;
    }

    /**
     * Check if the subscriber can listen to this subscription.
     *
     * @param Subscriber $subscriber
     *
     * @return bool
     */
    public function can(Subscriber $subscriber)
    {
        return true;
    }

    /**
     * Encode topic name.
     *
     * @param Subscriber $subscriber
     * @param string     $fieldName
     *
     * @return string
     */
    public function encodeTopic(Subscriber $subscriber, string $fieldName)
    {
        return strtoupper(snake_case($fieldName));
    }

    /**
     * Decode topic name.
     *
     * @param string $fieldName
     * @param mixed  $root
     *
     * @return string
     */
    public function decodeTopic(string $fieldName, $root)
    {
        return strtoupper(snake_case($fieldName));
    }
}
