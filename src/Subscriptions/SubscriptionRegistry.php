<?php declare(strict_types=1);

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
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Support\Contracts\SerializesContext;
use Nuwave\Lighthouse\Support\Utils;

class SubscriptionRegistry
{
    /**
     * A map from operation names to channel names.
     *
     * @var array<string, string>
     */
    protected array $subscribers = [];

    /**
     * Active subscription fields of the schema.
     *
     * @var array<string, \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription>
     */
    protected array $subscriptions = [];

    public function __construct(
        protected SerializesContext $serializer,
        protected StoresSubscriptions $storage,
        protected SchemaBuilder $schemaBuilder,
        protected ConfigRepository $configRepository,
    ) {}

    /** Add subscription to registry. */
    public function register(GraphQLSubscription $subscription, string $field): self
    {
        $this->subscriptions[$field] = $subscription;

        return $this;
    }

    /** Check if subscription is registered. */
    public function has(string $key): bool
    {
        if (isset($this->subscriptions[$key])) {
            return true;
        }

        return $this->subscriptionType()->hasField($key);
    }

    /** Get instance of subscription. */
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

    /** Add subscription to registry. */
    public function subscriber(Subscriber $subscriber, string $topic): self
    {
        $this->storage->storeSubscriber($subscriber, $topic);
        $this->subscribers[$subscriber->fieldName] = $subscriber->channel;

        return $this;
    }

    /**
     * Get registered subscriptions.
     *
     * @return \Illuminate\Support\Collection<int, \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription>
     */
    public function subscriptions(Subscriber $subscriber): Collection
    {
        return (new Collection($subscriber->query->definitions))
            ->filter(
                Utils::instanceofMatcher(OperationDefinitionNode::class),
            )
            // @phpstan-ignore-next-line type of $node was narrowed by the preceding filter
            ->filter(static fn (OperationDefinitionNode $node): bool => $node->operation === 'subscription')
            // @phpstan-ignore-next-line type of $node was narrowed by the preceding filter
            ->map(static fn (OperationDefinitionNode $node): array => (new Collection($node->selectionSet->selections))
                // @phpstan-ignore-next-line subscriptions must only have a single field
                ->map(static fn (FieldNode $field): string => $field->name->value)
                ->all())
            ->collapse()
            ->map(function (string $subscriptionField): GraphQLSubscription {
                if ($this->has($subscriptionField)) {
                    return $this->subscription($subscriptionField);
                }

                return new NotFoundSubscription();
            });
    }

    /** Reset the collection of subscribers when a new execution starts. */
    public function handleStartExecution(StartExecution $startExecution): void
    {
        $this->subscribers = [];
    }

    public function handleBuildExtensionsResponse(BuildExtensionsResponse $buildExtensionsResponse): ?ExtensionsResponse
    {
        $channel = $this->subscribers !== []
            ? reset($this->subscribers)
            : null;

        if ($channel === null && $this->configRepository->get('lighthouse.subscriptions.exclude_empty', false)) {
            return null;
        }

        return new ExtensionsResponse('lighthouse_subscriptions', [
            'channel' => $channel,
        ]);
    }

    protected function subscriptionType(): ObjectType
    {
        return $this->schemaBuilder->schema()->getSubscriptionType()
            ?? throw new DefinitionException('Schema is missing subscription root type.');
    }
}
