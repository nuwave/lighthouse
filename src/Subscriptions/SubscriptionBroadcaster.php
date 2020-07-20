<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator;
use Nuwave\Lighthouse\Subscriptions\Events\BroadcastSubscriptionEvent;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionBroadcaster implements BroadcastsSubscriptions
{
    /**
     * @var \Nuwave\Lighthouse\GraphQL
     */
    protected $graphQL;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions
     */
    protected $auth;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions
     */
    protected $storage;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator
     */
    protected $iterator;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\BroadcastManager
     */
    protected $broadcastManager;

    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $eventsDispatcher;

    public function __construct(
        GraphQL $graphQL,
        AuthorizesSubscriptions $auth,
        StoresSubscriptions $storage,
        SubscriptionIterator $iterator,
        BroadcastManager $broadcastManager,
        EventsDispatcher $eventsDispatcher
    ) {
        $this->graphQL = $graphQL;
        $this->auth = $auth;
        $this->storage = $storage;
        $this->iterator = $iterator;
        $this->broadcastManager = $broadcastManager;
        $this->eventsDispatcher = $eventsDispatcher;
    }

    /**
     * Queue pushing subscription data to subscribers.
     */
    public function queueBroadcast(GraphQLSubscription $subscription, string $fieldName, $root): void
    {
        // TODO replace with a job dispatch in v5
        $this->eventsDispatcher->dispatch(
            new BroadcastSubscriptionEvent($subscription, $fieldName, $root)
        );
    }

    /**
     * Push subscription data to subscribers.
     */
    public function broadcast(GraphQLSubscription $subscription, string $fieldName, $root): void
    {
        $topic = $subscription->decodeTopic($fieldName, $root);

        $subscribers = $this->storage
            ->subscribersByTopic($topic)
            ->filter(function (Subscriber $subscriber) use ($subscription, $root): bool {
                return $subscription->filter($subscriber, $root);
            });

        $this->iterator->process(
            $subscribers,
            function (Subscriber $subscriber) use ($root): void {
                $data = $this->graphQL->executeQuery(
                    $subscriber->query,
                    $subscriber->context,
                    $subscriber->args,
                    $subscriber->setRoot($root),
                    $subscriber->operationName
                );

                $this->broadcastManager->broadcast(
                    $subscriber,
                    $data->jsonSerialize()
                );
            }
        );
    }

    /**
     * Authorize the subscription.
     */
    public function authorize(Request $request): Response
    {
        return $this->auth->authorize($request)
            ? $this->broadcastManager->authorized($request)
            : $this->broadcastManager->unauthorized($request);
    }
}
