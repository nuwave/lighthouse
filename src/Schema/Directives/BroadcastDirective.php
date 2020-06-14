<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Deferred;
use Nuwave\Lighthouse\Execution\Utils\Subscription;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class BroadcastDirective extends BaseDirective implements FieldMiddleware, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
directive @broadcast(
  """
  Name of the subscription that should be retriggered as a result of this operation..
  """
  subscription: String!

  """
  Specify whether or not the job should be queued.
  This defaults to the global config option `lighthouse.subscriptions.queue_broadcasts`.
  """
  shouldQueue: Boolean
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Resolve the field directive.
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        // Ensure this is run after the other field middleware directives
        $fieldValue = $next($fieldValue);
        $resolver = $fieldValue->getResolver();

        return $fieldValue->setResolver(function () use ($resolver) {
            $resolved = call_user_func_array($resolver, func_get_args());

            $subscriptionField = $this->directiveArgValue('subscription');
            $shouldQueue = $this->directiveArgValue('shouldQueue');

            if ($resolved instanceof Deferred) {
                $resolved->then(function ($root) use ($subscriptionField, $shouldQueue): void {
                    Subscription::broadcast($subscriptionField, $root, $shouldQueue);
                });
            } else {
                Subscription::broadcast($subscriptionField, $resolved, $shouldQueue);
            }

            return $resolved;
        });
    }
}
