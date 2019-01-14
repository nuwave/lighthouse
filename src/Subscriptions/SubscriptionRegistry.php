<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Support\Arr;
use GraphQL\Language\Parser;
use GraphQL\Language\AST\Node;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Schema\Types\NotFoundSubscription;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;

class SubscriptionRegistry
{
    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer
     */
    protected $serializer;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\StorageManager
     */
    protected $storage;

    /**
     * A map from operation names to channel names.
     *
     * @var string[]
     */
    protected $subscribers = [];

    /**
     * Active subscription fields of the schema.
     *
     * @var \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription[]
     */
    protected $subscriptions = [];

    /**
     * @param  \Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer  $serializer
     * @param  \Nuwave\Lighthouse\Subscriptions\StorageManager  $storage
     * @return void
     */
    public function __construct(ContextSerializer $serializer, StorageManager $storage)
    {
        $this->serializer = $serializer;
        $this->storage = $storage;
    }

    /**
     * Add subscription to registry.
     *
     * @param  \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription  $subscription
     * @param  string  $field
     * @return $this
     */
    public function register(GraphQLSubscription $subscription, string $field): self
    {
        $this->subscriptions[$field] = $subscription;

        return $this;
    }

    /**
     * Check if subscription is registered.
     *
     * @param  string  $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->subscriptions[$key]);
    }

    /**
     * Get subscription keys.
     *
     * @return string[]
     */
    public function keys(): array
    {
        return array_keys($this->subscriptions);
    }

    /**
     * Get instance of subscription.
     *
     * @param  string  $key
     * @return \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription
     */
    public function subscription(string $key): GraphQLSubscription
    {
        return $this->subscriptions[$key];
    }

    /**
     * Add subscription to registry.
     *
     * @param  \Nuwave\Lighthouse\Subscriptions\Subscriber  $subscriber
     * @param  string  $channel
     * @return $this
     */
    public function subscriber(Subscriber $subscriber, string $channel): self
    {
        if ($subscriber->channel) {
            $this->storage->storeSubscriber($subscriber, $channel);
        }

        $this->subscribers[$subscriber->operationName] = $subscriber->channel;

        return $this;
    }

    /**
     * Get registered subscriptions.
     *
     * @param  \Nuwave\Lighthouse\Subscriptions\Subscriber  $subscriber
     * @return \Illuminate\Support\Collection
     *
     * @throws \GraphQL\Error\SyntaxError
     */
    public function subscriptions(Subscriber $subscriber): Collection
    {
        // A subscription can be fired w/out a request so we must make
        // sure the schema has been generated.
        app('graphql')->prepSchema();

        $documentNode = Parser::parse($subscriber->queryString, [
            'noLocation' => true,
        ]);

        return collect($documentNode->definitions)
            ->filter(function (Node $node): bool {
                return $node instanceof OperationDefinitionNode;
            })
            ->filter(function (OperationDefinitionNode $node): bool {
                return $node->operation === 'subscription';
            })
            ->flatMap(function (OperationDefinitionNode $node) {
                return collect($node->selectionSet->selections)
                    ->map(function (FieldNode $field): string {
                        return $field->name->value;
                    })
                    ->toArray();
            })
            ->map(function ($subscriptionField): GraphQLSubscription {
                return Arr::get(
                    $this->subscriptions,
                    $subscriptionField,
                    new NotFoundSubscription()
                );
            });
    }

    /**
     * Get all current subscribers.
     *
     * @return string[]
     */
    public function toArray(): array
    {
        return $this->subscribers;
    }

    /**
     * Reset collection of subscribers.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->subscribers = [];
    }
}
