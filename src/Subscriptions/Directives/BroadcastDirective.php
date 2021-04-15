<?php

namespace Nuwave\Lighthouse\Subscriptions\Directives;

use Closure;
use Nuwave\Lighthouse\Execution\Utils\Subscription;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class BroadcastDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Broadcast the results of a mutation to subscribed clients.
"""
directive @broadcast(
  """
  Name of the subscription that should be retriggered as a result of this operation.
  """
  subscription: String!

  """
  Specify whether or not the job should be queued.
  This defaults to the global config option `lighthouse.subscriptions.queue_broadcasts`.
  """
  shouldQueue: Boolean
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        // Ensure this is run after the other field middleware directives
        $fieldValue = $next($fieldValue);

        $subscriptionField = $this->directiveArgValue('subscription');
        $shouldQueue = $this->directiveArgValue('shouldQueue');

        $fieldValue->resultHandler(function ($root) use ($subscriptionField, $shouldQueue) {
            Subscription::broadcast($subscriptionField, $root, $shouldQueue);

            return $root;
        });

        return $fieldValue;
    }
}
