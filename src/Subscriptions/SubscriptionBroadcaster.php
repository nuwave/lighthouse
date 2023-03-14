<?php declare(strict_types=1);

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
    public function __construct(
        protected GraphQL $graphQL,
        protected AuthorizesSubscriptions $subscriptionAuthorizer,
        protected StoresSubscriptions $subscriptionStorage,
        protected SubscriptionIterator $subscriptionIterator,
        protected BroadcastManager $broadcastManager,
        protected BusDispatcher $busDispatcher,
    ) {}

    public function queueBroadcast(GraphQLSubscription $subscription, string $fieldName, mixed $root): void
    {
        $broadcastSubscriptionJob = new BroadcastSubscriptionJob($subscription, $fieldName, $root);
        $broadcastSubscriptionJob->onQueue(config('lighthouse.subscriptions.broadcasts_queue_name'));

        $this->busDispatcher->dispatch($broadcastSubscriptionJob);
    }

    public function broadcast(GraphQLSubscription $subscription, string $fieldName, mixed $root): void
    {
        $topic = $subscription->decodeTopic($fieldName, $root);

        $subscribers = $this->subscriptionStorage
            ->subscribersByTopic($topic)
            ->filter(static fn (Subscriber $subscriber): bool => $subscription->filter($subscriber, $root));

        $this->subscriptionIterator->process(
            $subscribers,
            function (Subscriber $subscriber) use ($root): void {
                $subscriber->root = $root;

                $result = $this->graphQL->executeParsedQuery(
                    $subscriber->query,
                    $subscriber->context,
                    $subscriber->variables,
                    $subscriber,
                );
                $this->broadcastManager->broadcast($subscriber, $result);
            },
        );
    }

    public function authorize(Request $request): Response
    {
        return $this->subscriptionAuthorizer->authorize($request)
            ? $this->broadcastManager->authorized($request)
            : $this->broadcastManager->unauthorized($request);
    }
}
