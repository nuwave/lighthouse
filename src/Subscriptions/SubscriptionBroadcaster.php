<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Pusher\Pusher;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions as Auth;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator as Iterator;
use Nuwave\Lighthouse\Subscriptions\Events\BroadcastSubscriptionEvent as Event;

class SubscriptionBroadcaster implements BroadcastsSubscriptions
{
    /**
     * @var Auth
     */
    protected $auth;

    /**
     * @var StorageManager
     */
    protected $storage;

    /**
     * @var Iterator
     */
    protected $iterator;

    /**
     * @var BroadcastManager
     */
    protected $broadcastManager;

    /**
     * @param Auth             $auth
     * @param StorageManager   $storage
     * @param Iterator         $iterator
     * @param BroadcastManager $broadcastManager
     */
    public function __construct(
        Auth $auth,
        StorageManager $storage,
        Iterator $iterator,
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
     * @param GraphQLSubscription $subscription
     * @param string              $fieldName
     * @param mixed               $root
     */
    public function queueBroadcast(GraphQLSubscription $subscription, string $fieldName, $root)
    {
        event(new Event($subscription, $fieldName, $root));
    }

    /**
     * Push subscription data to subscribers.
     *
     * @param GraphQLSubscription $subscription
     * @param string              $fieldName
     * @param mixed               $root
     */
    public function broadcast(GraphQLSubscription $subscription, string $fieldName, $root)
    {
        info('broadcast.subscription');
        $topic = $subscription->decodeTopic($fieldName, $root);
        info('subcsription.topic: '.$topic);

        $subscribers = $this->storage
            ->subscribersByTopic($topic)
            ->filter(function (Subscriber $subscriber) use ($subscription, $root) {
                return $subscription->filter($subscriber, $root);
            });

        info('subscribers', $subscribers->toArray());

        $this->iterator->process(
            $subscribers,
            function (Subscriber $subscriber) use ($root) {
                $data = graphql()->executeQuery(
                    $subscriber->queryString,
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
     * @return array
     */
    public function authorize(Request $request)
    {
        return $this->auth->authorize($request)
            ? $this->broadcastManager->authorized($request)
            : $this->broadcastManager->unauthorized($request);
    }
}
