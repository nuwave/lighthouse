<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator;
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
    protected $subscriptionAuthorizer;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions
     */
    protected $subscriptionStorage;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator
     */
    protected $subscriptionIterator;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\BroadcastManager
     */
    protected $broadcastManager;

    /**
     * @var \Illuminate\Contracts\Bus\Dispatcher
     */
    protected $busDispatcher;

    public function __construct(
        GraphQL $graphQL,
        AuthorizesSubscriptions $subscriptionAuthorizer,
        StoresSubscriptions $subscriptionStorage,
        SubscriptionIterator $subscriptionIterator,
        BroadcastManager $broadcastManager,
        BusDispatcher $busDispatcher
    ) {
        $this->graphQL = $graphQL;
        $this->subscriptionAuthorizer = $subscriptionAuthorizer;
        $this->subscriptionStorage = $subscriptionStorage;
        $this->subscriptionIterator = $subscriptionIterator;
        $this->broadcastManager = $broadcastManager;
        $this->busDispatcher = $busDispatcher;
    }

    public function queueBroadcast(GraphQLSubscription $subscription, string $fieldName, $root): void
    {
        $broadcastSubscriptionJob = new BroadcastSubscriptionJob($subscription, $fieldName, $root);
        $broadcastSubscriptionJob->onQueue(config('lighthouse.subscriptions.broadcasts_queue_name'));

        $this->busDispatcher->dispatch($broadcastSubscriptionJob);
    }

    public function broadcast(GraphQLSubscription $subscription, string $fieldName, $root): void
    {
        $topic = $subscription->decodeTopic($fieldName, $root);

        $subscribers = $this->subscriptionStorage
            ->subscribersByTopic($topic)
            ->filter(function (Subscriber $subscriber) use ($subscription, $root): bool {
                return $subscription->filter($subscriber, $root);
            });

        $this->subscriptionIterator->process(
            $subscribers,
            function (Subscriber $subscriber) use ($root): void {
                $subscriber->root = $root;

                $executionResult = $this->graphQL->executeParsedQuery(
                    $subscriber->query,
                    $subscriber->context,
                    $subscriber->variables,
                    $subscriber
                );

                $this->broadcastManager->broadcast(
                    $subscriber,
                    $this->graphQL->serializable($executionResult)
                );
            }
        );
    }

    public function authorize(Request $request): Response
    {
        return $this->subscriptionAuthorizer->authorize($request)
            ? $this->broadcastManager->authorized($request)
            : $this->broadcastManager->unauthorized($request);
    }
}
