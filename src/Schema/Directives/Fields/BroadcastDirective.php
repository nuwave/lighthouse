<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Closure;
use GraphQL\Deferred;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\Utils\Subscription;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class BroadcastDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'broadcast';
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $value
     * @param  \Closure  $next
     *
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $value, Closure $next): FieldValue
    {
        $value = $next($value);
        $resolver = $value->getResolver();
        $subscriptionField = $this->directiveArgValue('subscription');
        $shouldQueue = $this->directiveArgValue('shouldQueue');

        return $value->setResolver(function () use ($resolver, $subscriptionField, $shouldQueue) {
            $resolved = call_user_func_array($resolver, func_get_args());

            if ($resolved instanceof Deferred) {
                $resolved->then(function ($root) use ($subscriptionField, $shouldQueue) {
                    Subscription::broadcast($subscriptionField, $root, $shouldQueue);
                });
            } else {
                Subscription::broadcast($subscriptionField, $resolved, $shouldQueue);
            }

            return $resolved;
        });
    }
}
