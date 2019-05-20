<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Deferred;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\Utils\Subscription;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class BroadcastDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'broadcast';
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
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
