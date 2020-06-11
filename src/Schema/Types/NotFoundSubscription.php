<?php

namespace Nuwave\Lighthouse\Schema\Types;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class NotFoundSubscription extends GraphQLSubscription
{
    /**
     * Authorize subscriber request.
     */
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        return false;
    }

    /**
     * Filter which subscribers should receive the subscription.
     */
    public function filter(Subscriber $subscriber, $root): bool
    {
        return false;
    }

    /**
     * Resolve the subscription.
     */
    public function resolve($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): void
    {
    }
}
