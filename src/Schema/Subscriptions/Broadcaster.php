<?php

namespace Nuwave\Lighthouse\Schema\Subscriptions;

use Pusher\Pusher;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Schema\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Schema\Subscriptions\Contracts\StoresSubscriptions as Storage;
use Nuwave\Lighthouse\Schema\Subscriptions\Contracts\AuthorizesSubscriptions as Auth;
use Nuwave\Lighthouse\Schema\Subscriptions\Contracts\SubscriptionIterator as Iterator;

class Broadcaster implements BroadcastsSubscriptions
{
    const EVENT_NAME = 'lighthouse-subscription';

    /**
     * @var Auth
     */
    protected $auth;

    /**
     * @var Pusher
     */
    protected $pusher;

    /**
     * @var Storage
     */
    protected $storage;

    /**
     * @var Iterator
     */
    protected $iterator;

    /**
     * @param Auth     $auth
     * @param Pusher   $pusher
     * @param Storage  $storage
     * @param Iterator $iterator
     */
    public function __construct(
        Auth $auth,
        Pusher $pusher,
        Storage $storage,
        Iterator $iterator
    ) {
        $this->auth = $auth;
        $this->pusher = $pusher;
        $this->storage = $storage;
        $this->iterator = $iterator;
    }

    /**
     * Broadcast subscription data.
     *
     * @param GraphQLSubscription $subscription
     * @param string              $fieldName
     * @param mixed               $root
     */
    public function broadcast(GraphQLSubscription $subscription, string $fieldName, $root)
    {
        $this->push(
            $subscription->decodeTopic($fieldName, $root),
            $root
        );
    }

    /**
     * Push subscription data to subscribers.
     *
     * @param string $topic
     * @param mixed  $root
     */
    public function push(string $topic, $root)
    {
        $this->iterator->process(
            $this->storage->subscribersByTopic($topic),
            function (Subscriber $subscriber) use ($root) {
                $data = graphql()->execute(
                    $subscriber->queryString,
                    $subscriber->context,
                    $subscriber->args,
                    $subscriber->setRoot($root)
                );

                $this->pusher->trigger($subscriber->channel, self::EVENT_NAME, [
                    'more' => true,
                    'result' => $data,
                ]);
            }
        );
    }

    /**
     * Authorize the subscription.
     *
     * @param string  $channel
     * @param string  $socketId
     * @param Request $request
     *
     * @return array
     */
    public function authorize($channel, $socketId, Request $request)
    {
        if (! $this->auth->authorize($channel, $request)) {
            $this->storage->deleteSubscriber($channel);

            return ['error' => 'unauthorized'];
        }

        return json_decode(
            $this->pusher->socket_auth($channel, $socketId),
            true
        );
    }
}
