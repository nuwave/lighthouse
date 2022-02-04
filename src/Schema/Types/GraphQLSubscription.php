<?php

namespace Nuwave\Lighthouse\Schema\Types;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class GraphQLSubscription
{
    /**
     * Check if subscriber is allowed to listen to this subscription.
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
     * @return string
     */
    public function encodeTopic(Subscriber $subscriber, string $fieldName)
    {
        return strtoupper(
            Str::snake($fieldName)
        );
    }

    /**
     * Decode topic name.
     *
     * @param  mixed  $root  the root value
     *
     * @return string
     */
    public function decodeTopic(string $fieldName, $root)
    {
        return strtoupper(
            Str::snake($fieldName)
        );
    }

    /**
     * Resolve the subscription.
     *
     * @param  mixed  $root  the root value
     * @param  array<string, mixed>  $args
     *
     * @return mixed the root value
     */
    public function resolve($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return $root;
    }

    /**
     * Check if subscriber is allowed to listen to the subscription.
     *
     * @return bool
     */
    abstract public function authorize(Subscriber $subscriber, Request $request);

    /**
     * Filter which subscribers should receive the subscription.
     *
     * @param  mixed  $root  the root value
     *
     * @return bool
     */
    abstract public function filter(Subscriber $subscriber, $root);
}
