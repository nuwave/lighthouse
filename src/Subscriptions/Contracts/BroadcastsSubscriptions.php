<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Symfony\Component\HttpFoundation\Response;

interface BroadcastsSubscriptions
{
    /** Push subscription data to subscribers. */
    public function broadcast(GraphQLSubscription $subscription, string $fieldName, mixed $root): void;

    /** Queue pushing subscription data to subscribers. */
    public function queueBroadcast(GraphQLSubscription $subscription, string $fieldName, mixed $root): void;

    /** Authorize the subscription. */
    public function authorize(Request $request): Response;
}
