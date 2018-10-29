<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Deferred;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry as Registry;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Support\Contracts\SubscriptionExceptionHandler as ExceptionHandler;

class BroadcastDirective extends BaseDirective implements FieldMiddleware
{
    /** @var Registry */
    protected $registry;

    /** @var BroadcastsSubscriptions */
    protected $broadcaster;

    /** @var ExceptionHandler */
    protected $exceptionHandler;

    /**
     * @param Registry                $registry
     * @param BroadcastsSubscriptions $broadcaster
     * @param ExceptionHandler        $exceptionHandler
     */
    public function __construct(
        Registry $registry,
        BroadcastsSubscriptions $broadcaster,
        ExceptionHandler $exceptionHandler
    ) {
        $this->registry = $registry;
        $this->broadcaster = $broadcaster;
        $this->exceptionHandler = $exceptionHandler;
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
                $this->exceptionHandler->handleBroadcastError($e);
            }

            return $resolved;
        });
    }
}
