<?php

namespace Nuwave\Lighthouse\Schema\Values;

use Closure;
use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Support\Utils;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\AST\StringValueNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;
use Nuwave\Lighthouse\Schema\Conversion\DefinitionNodeConverter;
use Nuwave\Lighthouse\Subscriptions\Exceptions\UnauthorizedSubscriber;

class FieldValue
{
    /**
     * An instance of the type that this field returns.
     *
     * @var \GraphQL\Type\Definition\Type|null
     */
    protected $returnType;

    /**
     * The underlying AST definition of the Field.
     *
     * @var \GraphQL\Language\AST\FieldDefinitionNode
     */
    protected $field;

    /**
     * The parent type of the field.
     *
     * @var \Nuwave\Lighthouse\Schema\Values\NodeValue
     */
    protected $parent;

    /**
     * The actual field resolver.
     *
     * @var \Closure|null
     */
    protected $resolver;

    /**
     * Text describing by this field is deprecated.
     *
     * @var string|null
     */
    protected $deprecationReason = null;

    /**
     * A closure that determines the complexity of executing the field.
     *
     * @var \Closure
     */
    protected $complexity;

    /**
     * Cache key should be private.
     *
     * @var bool
     */
    protected $privateCache = false;

    /**
     * Create new field value instance.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\NodeValue  $parent
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $field
     * @return void
     */
    public function __construct(NodeValue $parent, FieldDefinitionNode $field)
    {
        $this->parent = $parent;
        $this->field = $field;
    }

    /**
     * Overwrite the current/default resolver.
     *
     * @param  \Closure  $resolver
     * @return $this
     */
    public function setResolver(Closure $resolver): self
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Define a closure that is used to determine the complexity of the field.
     *
     * @param  \Closure  $complexity
     * @return $this
     */
    public function setComplexity(Closure $complexity): self
    {
        $this->complexity = $complexity;

        return $this;
    }

    /**
     * Set deprecation reason for field.
     *
     * @param  string  $deprecationReason
     * @return $this
     */
    public function setDeprecationReason(string $deprecationReason): self
    {
        $this->deprecationReason = $deprecationReason;

        return $this;
    }

    /**
     * Get an instance of the return type of the field.
     *
     * @return \GraphQL\Type\Definition\Type
     */
    public function getReturnType(): Type
    {
        if (! isset($this->returnType)) {
            $this->returnType = app(DefinitionNodeConverter::class)->toType(
                $this->field->type
            );
        }

        return $this->returnType;
    }

    /**
     * @return \Nuwave\Lighthouse\Schema\Values\NodeValue
     */
    public function getParent(): NodeValue
    {
        return $this->parent;
    }

    /**
     * @return string
     */
    public function getParentName(): string
    {
        return $this->getParent()->getTypeDefinitionName();
    }

    /**
     * Get the underlying AST definition for the field.
     *
     * @return \GraphQL\Language\AST\FieldDefinitionNode
     */
    public function getField(): FieldDefinitionNode
    {
        return $this->field;
    }

    /**
     * Get field resolver.
     *
     * @return \Closure
     */
    public function getResolver(): Closure
    {
        if (! isset($this->resolver)) {
            $this->resolver = $this->defaultResolver();
        }

        return $this->resolver;
    }

    /**
     * Get default field resolver.
     *
     * @return \Closure
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    protected function defaultResolver(): Closure
    {
        if ($this->getParentName() === 'Subscription') {
            return $this->defaultSubscriptionResolver();
        }

        if ($this->parentIsRootType()) {
            $resolverClass = Utils::namespaceClassname(
                studly_case($this->getFieldName()),
                $this->defaultNamespacesForParent(),
                function (string $class): bool {
                    return method_exists($class, 'resolve');
                }
            );

            if (! $resolverClass) {
                throw new DefinitionException(
                    "Could not locate a default resolver for the field {$this->field->name->value}"
                );
            }

            return Closure::fromCallable(
                [app($resolverClass), 'resolve']
            );
        }

        return Closure::fromCallable(
            [Executor::class, 'defaultFieldResolver']
        );
    }

    /**
     * Get the default resolver for a subscription field.
     *
     * @return \Closure
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    protected function defaultSubscriptionResolver(): Closure
    {
        if ($directive = ASTHelper::directiveDefinition($this->field, 'subscription')) {
            $className = ASTHelper::directiveArgValue($directive, 'class');
        } else {
            $className = studly_case($this->getFieldName());
        }

        $className = Utils::namespaceClassname(
            $className,
            $this->defaultNamespacesForParent(),
            function (string $class): bool {
                return is_subclass_of($class, GraphQLSubscription::class);
            }
        );

        if (! $className) {
            throw new DefinitionException(
                "No class found for the subscription field {$this->getFieldName()}"
            );
        }

        /** @var \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription $subscription */
        $subscription = app($className);
        /** @var \Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry $subscriptionRegistry */
        $subscriptionRegistry = app(SubscriptionRegistry::class);

        // Subscriptions can only be placed on a single field on the root
        // query, so there is no need to consider the field path
        $subscriptionRegistry->register(
            $subscription,
            $this->getFieldName()
        );

        return function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($subscription, $subscriptionRegistry) {
            if ($root instanceof Subscriber) {
                return $subscription->resolve($root->root, $args, $context, $resolveInfo);
            }

            $subscriber = new Subscriber(
                $args,
                $context,
                $resolveInfo
            );

            if (! $subscription->can($subscriber)) {
                throw new UnauthorizedSubscriber(
                    'Unauthorized subscription request'
                );
            }

            $subscriptionRegistry->subscriber(
                $subscriber,
                $subscription->encodeTopic($subscriber, $this->getFieldName())
            );
        };
    }

    /**
     * Return the namespaces configured for the parent type.
     *
     * @return string[]
     */
    public function defaultNamespacesForParent(): array
    {
        switch ($this->getParentName()) {
            case 'Query':
                return (array) config('lighthouse.namespaces.queries');
            case 'Mutation':
                return (array) config('lighthouse.namespaces.mutations');
            case 'Subscription':
                return (array) config('lighthouse.namespaces.subscriptions');
            default:
               return [];
        }
    }

    /**
     * @return \GraphQL\Language\AST\StringValueNode|null
     */
    public function getDescription(): ?StringValueNode
    {
        return $this->field->description;
    }

    /**
     * Get current complexity.
     *
     * @return \Closure|null
     */
    public function getComplexity(): ?Closure
    {
        return $this->complexity;
    }

    /**
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->field->name->value;
    }

    /**
     * @return string|null
     */
    public function getDeprecationReason(): ?string
    {
        return $this->deprecationReason;
    }

    /**
     * Is the parent of this field one of the root types?
     *
     * @return bool
     */
    protected function parentIsRootType(): bool
    {
        return in_array(
            $this->getParentName(),
            ['Query', 'Mutation', 'Subscription']
        );
    }
}
