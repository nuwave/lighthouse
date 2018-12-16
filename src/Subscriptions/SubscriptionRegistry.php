<?php

namespace Nuwave\Lighthouse\Subscriptions;

use GraphQL\GraphQL;
use GraphQL\Language\AST\Node;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use Nuwave\Lighthouse\Schema\Fields\SubscriptionField;
use Nuwave\Lighthouse\Schema\Fields\NotFoundSubscriptionField;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;

class SubscriptionRegistry
{
    /**
     * @var ContextSerializer
     */
    protected $serializer;

    /**
     * @var SubscriptionStorage
     */
    protected $storage;

    /**
     * A map from operation name to channel name.
     *
     * E.g.
     * [
     *   'MyPostSubscription' => 'private-12398121248124-ysdafsd',
     *   'PostSub2' => 'private-190asofhdföaoshdfoa-asdf0afjs',
     * ]
     *
     * @var string[]
     */
    protected $subscribers = [];

    /**
     * The subscriptions themselves, keyed by the field name.
     *
     * E.g. ['onPostCreated' => SomeClass that is an instanceof GraphQlSubscription]
     *
     * @var SubscriptionField[]
     */
    protected $subscriptions = [];

    /**
     * @param ContextSerializer   $serializer
     * @param SubscriptionStorage $storage
     */
    public function __construct(ContextSerializer $serializer, SubscriptionStorage $storage)
    {
        $this->serializer = $serializer;
        $this->storage = $storage;
    }

    /**
     * Add subscription to registry.
     *
     * @param SubscriptionField $subscription
     * @param string            $fieldName
     *
     * @return SubscriptionRegistry
     */
    public function registerSubscription(SubscriptionField $subscription, string $fieldName): SubscriptionRegistry
    {
        $this->subscriptions[$fieldName] = $subscription;

        return $this;
    }

    /**
     * Check if subscription is registered.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->subscriptions[$key]);
    }

    /**
     * Get subscription keys.
     *
     * @return array
     */
    public function keys(): array
    {
        return array_keys($this->subscriptions);
    }

    /**
     * Get instance of subscription.
     *
     * @param string $key
     *
     * @return SubscriptionField
     */
    public function subscription(string $key): SubscriptionField
    {
        return $this->subscriptions[$key];
    }

    /**
     * Add subscription to registry.
     *
     * @param Subscriber $subscriber
     * @param string     $topic
     *
     * @return SubscriptionRegistry
     */
    public function registerSubscriber(Subscriber $subscriber, string $topic): SubscriptionRegistry
    {
        if ($subscriber->channel) {
            $this->storage->storeSubscriber($subscriber, $topic);
        }

        $this->subscribers[$subscriber->operationName] = $subscriber->channel;

        return $this;
    }

    /**
     * Get all subscriptions for a given subscriber.
     *
     * @param Subscriber $subscriber
     *
     * @return Collection<SubscriptionField>
     */
    public function subscriptions(Subscriber $subscriber): Collection
    {
        // A subscription can be fired without a request so we must make
        // sure the schema has been generated.
        app(GraphQL::class)->prepSchema();

        return collect($subscriber->query->definitions)
            ->filter(function (Node $node) {
                return $node instanceof OperationDefinitionNode;
            })
            ->filter(function (OperationDefinitionNode $node) {
                return 'subscription' === $node->operation;
            })
            ->flatMap(function (OperationDefinitionNode $node) {
                return collect($node->selectionSet->selections)
                    ->map(function (FieldNode $field) {
                        return $field->name->value;
                    })
                    ->toArray();
            })
            ->map(function (string $subscriptionField) {
                return array_get(
                    $this->subscriptions,
                    $subscriptionField,
                    new NotFoundSubscriptionField()
                );
            });
    }

    /**
     * Get the subscribers for the current request.
     *
     * @return string[]
     */
    public function subscribers(): array
    {
        return $this->subscribers;
    }

    /**
     * Reset the currently held subscribers.
     *
     * @return void
     */
    public function resetSubscribers()
    {
        $this->subscribers = [];
    }
}
