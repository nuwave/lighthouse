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
    public function name(): string
    {
        return 'broadcast';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     * @param \Closure   $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next): FieldValue
    {
        $value = $next($value);
        $resolver = $value->getResolver();
        $subscriptionField = $this->directiveArgValue('subscription');
        $shouldQueue = $this->directiveArgValue('shouldQueue');

        return $value->setResolver(function () use ($resolver, $subscriptionField, $shouldQueue) {
            $result = call_user_func_array($resolver, func_get_args());

            if ($result instanceof Deferred) {
                $result->then(function ($result) use ($subscriptionField, $shouldQueue) {
                    Subscription::broadcast($subscriptionField, $result, $shouldQueue);
                });
            } else {
                Subscription::broadcast($subscriptionField, $result, $shouldQueue);
            }

            return $result;
        });
    }
}
