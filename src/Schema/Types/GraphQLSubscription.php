<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Types;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class GraphQLSubscription
{
    /** Check if subscriber is allowed to listen to this subscription. */
    public function can(Subscriber $subscriber): bool
    {
        return true;
    }

    /** Encode topic name. */
    public function encodeTopic(Subscriber $subscriber, string $fieldName): string
    {
        return strtoupper(
            Str::snake($fieldName),
        );
    }

    /** Decode topic name. */
    public function decodeTopic(string $fieldName, mixed $root): string
    {
        return strtoupper(
            Str::snake($fieldName),
        );
    }

    /**
     * Resolve the subscription.
     *
     * @param  array<string, mixed>  $args
     */
    public function resolve(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): mixed
    {
        return $root;
    }

    /** Check if subscriber is allowed to listen to the subscription. */
    abstract public function authorize(Subscriber $subscriber, Request $request): bool;

    /** Filter which subscribers should receive the subscription. */
    abstract public function filter(Subscriber $subscriber, mixed $root): bool;
}
