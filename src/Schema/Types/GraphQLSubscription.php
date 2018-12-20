<?php

namespace Nuwave\Lighthouse\Schema\Types;

use Illuminate\Http\Request;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class GraphQLSubscription
{
    /**
     * Check if subscriber can listen to this subscription.
     *
     * @param Subscriber $subscriber
     *
     * @return bool
     */
    public function can(Subscriber $subscriber): bool
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
    public function encodeTopic(Subscriber $subscriber, string $fieldName): string
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
    public function decodeTopic(string $fieldName, $root): string
    {
        return strtoupper(snake_case($fieldName));
    }

    /**
     * Resolve the subscription.
     *
     * @param mixed          $root
     * @param array          $args
     * @param GraphQLContext $context
     * @param ResolveInfo    $info
     *
     * @return mixed
     */
    public function resolve($root, array $args, GraphQLContext $context, ResolveInfo $info)
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
    abstract public function authorize(Subscriber $subscriber, Request $request): bool;

    /**
     * Filter subscribers who should receive subscription.
     *
     * @param Subscriber $subscriber
     * @param mixed      $root
     *
     * @return bool
     */
    abstract public function filter(Subscriber $subscriber, $root): bool;
}
