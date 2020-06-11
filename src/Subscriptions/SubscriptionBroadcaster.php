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
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
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

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\CreatesContext
     */
    protected $createsContext;

    public function __construct(
        GraphQL $graphQL,
        AuthorizesSubscriptions $auth,
        StoresSubscriptions $storage,
        SubscriptionIterator $iterator,
        BroadcastManager $broadcastManager,
        EventsDispatcher $eventsDispatcher,
        CreatesContext $createsContext
    ) {
        $this->graphQL = $graphQL;
        $this->auth = $auth;
        $this->storage = $storage;
        $this->iterator = $iterator;
        $this->broadcastManager = $broadcastManager;
        $this->eventsDispatcher = $eventsDispatcher;
        $this->createsContext = $createsContext;
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

        if ($subscription->IS_PUBLIC) {
            $this->broadcastPublic($subscription, $fieldName, $root);

            return;
        }

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

    public function broadcastPublic(GraphQLSubscription $subscription, string $fieldName, $root): void
    {
        $topic = $subscription->decodeTopic($fieldName, $root);
        $subscriber = $this->storage->publicSubscriberForTopic($topic);
        if (! $subscriber) {
            throw new \Exception("no subscribers for public channel $topic $fieldName");
        }
        $channel_name = $subscription->getChannelName($subscriber->args);
        $data = $this->graphQL->executeQuery(
            $subscriber->query,
            $subscriber->context,
            $subscriber->args,
            $subscriber->setRoot($root),
            $subscriber->operationName
        );

        // swap public channel name in place of subscriber's private channel name
        $subscriber->channel = $channel_name;

        $this->broadcastManager->broadcast(
            $subscriber,
            $data->jsonSerialize()
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
