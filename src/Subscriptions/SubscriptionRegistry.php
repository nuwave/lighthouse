<?php

namespace Nuwave\Lighthouse\Subscriptions;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Definition\ObjectType;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ExtensionsResponse;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
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
     * @var \Nuwave\Lighthouse\Schema\SchemaBuilder
     */
    protected $schemaBuilder;

    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $configRepository;

    /**
     * A map from operation names to channel names.
     *
     * @var array<string, string>
     */
    protected $subscribers = [];

    /**
     * Active subscription fields of the schema.
     *
     * @var array<string, \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription>
     */
    protected $subscriptions = [];

    public function __construct(ContextSerializer $serializer, StoresSubscriptions $storage, SchemaBuilder $schemaBuilder, ConfigRepository $configRepository)
    {
        $this->serializer = $serializer;
        $this->storage = $storage;
        $this->schemaBuilder = $schemaBuilder;
        $this->configRepository = $configRepository;
    }

    /**
     * Add subscription to registry.
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
        if (isset($this->subscriptions[$key])) {
            return true;
        }

        return $this->subscriptionType()->hasField($key);
    }

    /**
     * Get subscription keys.
     *
     * @return array<string>
     *
     * @deprecated use the `GraphQL\Type\Schema::subscriptionType()->getFieldNames()` method directly
     */
    public function keys(): array
    {
        return $this->subscriptionType()->getFieldNames();
    }

    /**
     * Get instance of subscription.
     */
    public function subscription(string $key): GraphQLSubscription
    {
        if (! isset($this->subscriptions[$key])) {
            /**
             * Loading the field has the side effect of triggering a call to.
             *
             * @see \Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver::provideSubscriptionResolver()
             * which is then expected to call @see register().
             *
             * TODO make this more explicit and safe
             */
            $this->subscriptionType()->getField($key);
        }

        return $this->subscriptions[$key];
    }

    /**
     * Add subscription to registry.
     */
    public function subscriber(Subscriber $subscriber, string $topic): self
    {
        $this->storage->storeSubscriber($subscriber, $topic);
        $this->subscribers[$subscriber->fieldName] = $subscriber->channel;

        return $this;
    }

    /**
     * Get registered subscriptions.
     *
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Schema\Types\GraphQLSubscription>
     */
    public function subscriptions(Subscriber $subscriber): Collection
    {
        return (new Collection($subscriber->query->definitions))
            ->filter(
                Utils::instanceofMatcher(OperationDefinitionNode::class)
            )
            // @phpstan-ignore-next-line type of $node was narrowed by the preceding filter
            ->filter(function (OperationDefinitionNode $node): bool {
                return 'subscription' === $node->operation;
            })
            // @phpstan-ignore-next-line type of $node was narrowed by the preceding filter
            ->flatMap(function (OperationDefinitionNode $node): array {
                return (new Collection($node->selectionSet->selections))
                    // @phpstan-ignore-next-line subscriptions must only have a single field
                    ->map(function (FieldNode $field): string {
                        return $field->name->value;
                    })
                    ->all();
            })
            ->map(function (string $subscriptionField): GraphQLSubscription {
                if ($this->has($subscriptionField)) {
                    return $this->subscription($subscriptionField);
                }

                return new NotFoundSubscription();
            });
    }

    /**
     * Reset the collection of subscribers when a new execution starts.
     */
    public function handleStartExecution(StartExecution $startExecution): void
    {
        $this->subscribers = [];
    }

    public function handleBuildExtensionsResponse(BuildExtensionsResponse $buildExtensionsResponse): ?ExtensionsResponse
    {
        $subscriptionsConfig = $this->configRepository->get('lighthouse.subscriptions');

        $channel = count($this->subscribers) > 0
            ? reset($this->subscribers)
            : null;

        if (null === $channel && ($subscriptionsConfig['exclude_empty'] ?? false)) {
            return null;
        }

        $version = $subscriptionsConfig['version'] ?? 1;
        switch ((int) $version) {
            case 1:
                $content = [
                    'version' => 1,
                    'channel' => $channel,
                    'channels' => $this->subscribers,
                ];
                break;
            case 2:
                $content = [
                    'version' => 2,
                    'channel' => $channel,
                ];
                break;
            default:
                throw new DefinitionException("Expected lighthouse.subscriptions.version to be 1 or 2, got: {$version}");
        }

        return new ExtensionsResponse('lighthouse_subscriptions', $content);
    }

    protected function subscriptionType(): ObjectType
    {
        $subscriptionType = $this->schemaBuilder->schema()->getSubscriptionType();

        if (null === $subscriptionType) {
            throw new DefinitionException('Schema is missing subscription root type.');
        }

        return $subscriptionType;
    }
}
