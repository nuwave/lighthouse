<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Support\Arr;
use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\GraphQL;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\FieldNode;
use Nuwave\Lighthouse\Events\StartExecution;
use GraphQL\Language\AST\OperationDefinitionNode;
use Nuwave\Lighthouse\Execution\ExtensionsResponse;
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
     * @var \Nuwave\Lighthouse\GraphQL
     */
    protected $graphQL;

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
     * @param  \Nuwave\Lighthouse\GraphQL  $graphQL
     * @return void
     */
    public function __construct(ContextSerializer $serializer, StorageManager $storage, GraphQL $graphQL)
    {
        $this->serializer = $serializer;
        $this->storage = $storage;
        $this->graphQL = $graphQL;
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
     */
    public function subscriptions(Subscriber $subscriber): Collection
    {
        // A subscription can be fired w/out a request so we must make
        // sure the schema has been generated.
        $this->graphQL->prepSchema();

        return (new Collection($subscriber->query->definitions))
            ->filter(function (Node $node): bool {
                return $node instanceof OperationDefinitionNode;
            })
            ->filter(function (OperationDefinitionNode $node): bool {
                return $node->operation === 'subscription';
            })
            ->flatMap(function (OperationDefinitionNode $node) {
                return (new Collection($node->selectionSet->selections))
                    ->map(function (FieldNode $field): string {
                        return $field->name->value;
                    })
                    ->all();
            })
            ->map(function ($subscriptionField): GraphQLSubscription {
                return Arr::get(
                    $this->subscriptions,
                    $subscriptionField,
                    new NotFoundSubscription
                );
            });
    }

    /**
     * Reset the collection of subscribers when a new execution starts.
     *
     * @param  \Nuwave\Lighthouse\Events\StartExecution  $startExecution
     * @return void
     */
    public function handleStartExecution(StartExecution $startExecution): void
    {
        $this->subscribers = [];
    }

    /**
     * Get all current subscribers.
     *
     * @return \Nuwave\Lighthouse\Execution\ExtensionsResponse
     */
    public function handleBuildExtensionsResponse(): ExtensionsResponse
    {
        return new ExtensionsResponse(
            'lighthouse_subscriptions',
            [
                'version' => 1,
                'channels' => $this->subscribers,
            ]
        );
    }
}
