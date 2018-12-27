<?php

namespace Nuwave\Lighthouse\Schema\Types;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Context;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

abstract class GraphQLSubscription
{
    /**
     * Check if subscriber can listen to this subscription.
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
     *
     * @return string
     */
    public function encodeTopic(Subscriber $subscriber, $fieldName)
    {
        return strtoupper(snake_case($fieldName));
    }

    /**
     * Decode topic name.
     *
     * @param string $operationName
     * @param mixed  $root
     * @param mixed  $context
     *
     * @return string
     */
    public function decodeTopic(string $fieldName, $root)
    {
        return strtoupper(snake_case($fieldName));
    }

    /**
     * Resolve the subscription.
     *
     * @param mixed       $root
     * @param array       $args
     * @param Context     $context
     * @param ResolveInfo $info
     *
     * @return mixed
     */
    public function resolve($root, array $args, $context, ResolveInfo $info)
    {
        return $root;
    }

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
}
