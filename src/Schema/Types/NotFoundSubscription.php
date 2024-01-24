<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Types;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class NotFoundSubscription extends GraphQLSubscription
{
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        return false;
    }

    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        return false;
    }

    public function resolve(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): mixed
    {
        return null;
    }
}
