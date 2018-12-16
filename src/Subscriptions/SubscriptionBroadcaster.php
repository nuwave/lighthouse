<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nuwave\Lighthouse\Schema\Fields\SubscriptionField;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Events\BroadcastSubscriptionEvent;

class SubscriptionBroadcaster implements BroadcastsSubscriptions
{
    /**
     * @var AuthorizesSubscriptions
     */
    protected $auth;

    /**
     * @var SubscriptionStorage
     */
    protected $storage;

    /**
     * @var SubscriptionIterator
     */
    protected $iterator;

    /**
     * @var BroadcastManager
     */
    protected $broadcastManager;

    /**
     * @param AuthorizesSubscriptions $auth
     * @param SubscriptionStorage     $storage
     * @param SubscriptionIterator    $iterator
     * @param BroadcastManager        $broadcastManager
     */
    public function __construct(
        AuthorizesSubscriptions $auth,
        SubscriptionStorage $storage,
        SubscriptionIterator $iterator,
        BroadcastManager $broadcastManager
    ) {
        $this->auth = $auth;
        $this->storage = $storage;
        $this->iterator = $iterator;
        $this->broadcastManager = $broadcastManager;
    }

    /**
     * Queue pushing subscription data to subscribers.
     *
     * @param SubscriptionField $subscription
     * @param string            $fieldName
     * @param mixed             $result
     */
    public function queueBroadcast(SubscriptionField $subscription, string $fieldName, $result)
    {
        event(
            new BroadcastSubscriptionEvent($subscription, $fieldName, $result)
        );
    }

    /**
     * Push subscription data to subscribers.
     *
     * @param SubscriptionField $subscription
     * @param string            $fieldName
     * @param mixed             $root
     */
    public function broadcast(SubscriptionField $subscription, string $fieldName, $root)
    {
        $topic = $subscription->decodeTopic($fieldName, $root);

        $subscribers = $this->storage
            ->subscribersByTopic($topic)
            ->filter(function (Subscriber $subscriber) use ($subscription, $root) {
                return $subscription->filter($subscriber, $root);
            });

        $this->iterator->process(
            $subscribers,
            function (Subscriber $subscriber) use ($root) {
                $data = graphql()->executeQuery(
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
     *
     * @param Request $request
     *
     * @return Response
     */
    public function authorize(Request $request): Response
    {
        return $this->auth->authorize($request)
            ? $this->broadcastManager->authorized($request)
            : $this->broadcastManager->unauthorized($request);
    }
}
