<?php

namespace Nuwave\Lighthouse\Subscriptions;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Execution\ExtensionsResponse;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Schema\Types\NotFoundSubscription;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Support\Utils;

class SubscriptionRegistry
{
    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer
     */
    protected $serializer;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions
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

    public function __construct(ContextSerializer $serializer, StoresSubscriptions $storage, GraphQL $graphQL)
    {
        $this->serializer = $serializer;
        $this->storage = $storage;
        $this->graphQL = $graphQL;
    }

    /**
     * Add subscription to registry.
     *
     * @return $this
     */
    public function register(GraphQLSubscription $subscription, string $field): self
    {
        $this->subscriptions[$field] = $subscription;

        return $this;
    }

    /**
     * Check if subscription is registered.
     */
    public function has(string $key): bool
    {
        return isset($this->subscriptions[$key]);
    }

    /**
     * Get subscription keys.
     *
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->subscriptions);
    }

    /**
     * Get instance of subscription.
     */
    public function subscription(string $key): GraphQLSubscription
    {
        return $this->subscriptions[$key];
    }

    /**
     * Add subscription to registry.
     *
     * @param  \Nuwave\Lighthouse\Subscriptions\Subscriber  $subscriber
     * @return $this
     */
    public function subscriber(Subscriber $subscriber, string $topic): self
    {
        $this->storage->storeSubscriber($subscriber, $topic);
        $this->subscribers[$subscriber->operationName] = $subscriber->channel;

        return $this;
    }

    /**
     * Get registered subscriptions.
     *
     * @param  \Nuwave\Lighthouse\Subscriptions\Subscriber  $subscriber
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Schema\Types\GraphQLSubscription>
     */
    public function subscriptions(Subscriber $subscriber): Collection
    {
        // A subscription can be fired without a request so we must make
        // sure the schema has been generated.
        $this->graphQL->prepSchema();

        return (new Collection($subscriber->query->definitions))
            ->filter(
                Utils::instanceofMatcher(OperationDefinitionNode::class)
            )
            ->filter(function (OperationDefinitionNode $node): bool {
                return $node->operation === 'subscription';
            })
            ->flatMap(function (OperationDefinitionNode $node): array {
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
     */
    public function handleStartExecution(StartExecution $startExecution): void
    {
        $this->subscribers = [];
    }

    /**
     * Get all current subscribers.
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
