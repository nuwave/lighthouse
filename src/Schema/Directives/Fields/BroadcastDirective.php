<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Deferred;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry as Registry;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;

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
        $queueBroadcast = $this->directiveArgValue('queue', config('lighthouse.subscriptions.queue', false));
        $broadcastMethod = $queueBroadcast ? 'queueBroadcast' : 'broadcast';

        return $value->setResolver(function () use ($resolver, $subscriptionField, $broadcastMethod) {
            $resolved = call_user_func_array($resolver, func_get_args());

            try {
                $subscription = $this->registry->subscription($subscriptionField);

                if ($resolved instanceof Deferred) {
                    $resolved->then(function ($root) use ($subscription, $broadcastMethod) {
                        call_user_func(
                            [$this->broadcaster, $broadcastMethod],
                            $subscription,
                            $subscriptionField,
                            $root
                        );
                    });
                } else {
                    call_user_func(
                        [$this->broadcaster, $broadcastMethod],
                        $subscription,
                        $subscriptionField,
                        $resolved
                    );
                }
            } catch (\Exception $e) {
                // TODO: Create a BroadcastExceptionHandler so the implementation can be switched out.
                info('broadcast.exception', [
                    'message' => $e->getMessage(),
                    'stack' => $e->getTrace(),
                ]);
            }

            return $resolved;
        });
    }
}
