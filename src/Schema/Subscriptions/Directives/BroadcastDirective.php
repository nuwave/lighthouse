<?php

namespace Nuwave\Lighthouse\Schema\Subscriptions\Directives;

use GraphQL\Deferred;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Schema\Subscriptions\SubscriptionRegistry as Registry;
use Nuwave\Lighthouse\Schema\Subscriptions\Contracts\BroadcastsSubscriptions;

class BroadcastDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var BroadcastsSubscriptions
     */
    protected $broadcaster;

    /**
     * @param Registry                $registry
     * @param BroadcastsSubscriptions $broadcaster
     */
    public function __construct(Registry $registry, BroadcastsSubscriptions $broadcaster)
    {
        $this->registry = $registry;
        $this->broadcaster = $broadcaster;
    }

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
     * @param FieldValue $value
     * @param \Closure   $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next)
    {
        $value = $next($value);
        $resolver = $value->getResolver();
        $subscriptionField = $this->directiveArgValue('subscription');

        return $value->setResolver(function () use ($resolver, $subscriptionField) {
            $resolved = call_user_func_array($resolver, func_get_args());

            try {
                $subscription = $this->registry->subscription($subscriptionField);

                if ($resolved instanceof Deferred) {
                    $resolved->then(function ($root) use ($subscription) {
                        $this->broadcaster->broadcast($subscription, $subscriptionField, $root);
                    });
                } else {
                    $this->broadcaster->broadcast($subscription, $subscriptionField, $resolved);
                }
            } catch (\Exception $e) {
                // There was anIssue w/ broadcasting to subscribers but
                // we should not block this resolver.
                info('broadcast.exception', [
                    'message' => $e->getMessage(),
                    'stack' => $e->getTrace(),
                ]);
            }

            return $resolved;
        });
    }
}
