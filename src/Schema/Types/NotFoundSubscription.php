<?php

namespace Nuwave\Lighthouse\Schema\Types;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class NotFoundSubscription extends GraphQLSubscription
{
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        return false;
    }

    public function filter(Subscriber $subscriber, $root): bool
    {
        return false;
    }

    public function resolve($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): mixed
    {
        return null;
    }
}
