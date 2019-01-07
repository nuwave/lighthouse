<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

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
     * @param  FieldValue  $value
     * @param  \Closure   $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next): FieldValue
    {
        $value = $next($value);
        $resolver = $value->getResolver();
        $subscriptionField = $this->directiveArgValue('subscription');
        $queue = $this->directiveArgValue('queue');

        return $value->setResolver(function () use ($resolver, $subscriptionField, $queue) {
            $resolved = call_user_func_array($resolver, func_get_args());

            if ($resolved instanceof Deferred) {
                $resolved->then(function ($root) use ($subscriptionField, $queue) {
                    Subscription::broadcast($subscriptionField, $root, $queue);
                });
            } else {
                Subscription::broadcast($subscriptionField, $resolved, $queue);
            }

            return $resolved;
        });
    }
}
