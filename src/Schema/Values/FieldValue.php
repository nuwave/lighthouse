<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\AST\StringValueNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Fields\SubscriptionField;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;
use Nuwave\Lighthouse\Schema\Conversion\DefinitionNodeConverter;
use Nuwave\Lighthouse\Subscriptions\Exceptions\UnauthorizedSubscriber;

class FieldValue
{
    /**
     * An instance of the type that this field returns.
     *
     * @var Type|null
     */
    protected $returnType;

    /**
     * The underlying AST definition of the Field.
     *
     * @var FieldDefinitionNode
     */
    protected $field;

    /**
     * The parent type of the field.
     *
     * @var NodeValue
     */
    protected $parent;

    /**
     * The actual field resolver.
     *
     * @var \Closure|null
     */
    protected $resolver;

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
     * Additional args to inject into resolver.
     *
     * @var array
     */
    protected $additionalArgs = [];

    /**
     * Create new field value instance.
     *
     * @param NodeValue           $parent
     * @param FieldDefinitionNode $field
     */
    public function __construct(NodeValue $parent, FieldDefinitionNode $field)
    {
        $this->parent = $parent;
        $this->field = $field;
    }

    /**
     * Overwrite the current/default resolver.
     *
     * @param \Closure $resolver
     *
     * @return FieldValue
     */
    public function setResolver(\Closure $resolver): FieldValue
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Define a closure that is used to determine the complexity of the field.
     *
     * @param \Closure $complexity
     *
     * @return FieldValue
     */
    public function setComplexity(\Closure $complexity): FieldValue
    {
        $this->complexity = $complexity;

        return $this;
    }

    /**
     * Inject field argument.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return FieldValue
     */
    public function injectArg(string $key, $value): FieldValue
    {
        $this->additionalArgs = array_merge(
            $this->additionalArgs,
            [$key => $value]
        );

        return $this;
    }

    /**
     * @return array
     */
    public function getAdditionalArgs(): array
    {
        return $this->additionalArgs;
    }

    /**
     * Get an instance of the return type of the field.
     *
     * @return Type
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
     * @return NodeValue
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
     * @return FieldDefinitionNode
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
    public function getResolver(): \Closure
    {
        if (! isset($this->resolver)) {
            $this->resolver = $this->defaultResolver();
        }

        return $this->resolver;
    }

    /**
     * Get default field resolver.
     *
     * @throws DefinitionException
     *
     * @return \Closure
     */
    protected function defaultResolver(): \Closure
    {
        if ('Subscription' === $this->getParentName()) {
            return $this->defaultSubscriptionResolver();
        }

        if ($namespace = $this->getDefaultNamespaceForParent()) {
            return construct_resolver(
                $namespace.'\\'.studly_case($this->getFieldName()),
                'resolve'
            );
        }

        // TODO convert this back once we require PHP 7.1
        // return \Closure::fromCallable(
        //     [\GraphQL\Executor\Executor::class, 'defaultFieldResolver']
        // );
        return function () {
            return \GraphQL\Executor\Executor::defaultFieldResolver(...func_get_args());
        };
    }

    protected function defaultSubscriptionResolver()
    {
        if ($directive = ASTHelper::directiveDefinition($this->field, 'subscription')) {
            $className = ASTHelper::directiveArgValue($directive, 'class');
        } else {
            $className = $this->getFieldName();
        }

        $className = \namespace_classname($className, [
            $this->getDefaultNamespaceForParent(),
        ]);

        if (! $className) {
            throw new DefinitionException(
                "No class found for the subscription field {$this->getFieldName()}"
            );
        }

        /** @var SubscriptionField $subscription */
        $subscription = app($className);
        /** @var SubscriptionRegistry $subscriptionRegistry */
        $subscriptionRegistry = app(SubscriptionRegistry::class);

        // Subscriptions can only be placed on a single field on the root
        // query, so there is no need to consider the field path
        $subscriptionRegistry->registerSubscription(
            $subscription,
            $this->getFieldName()
        );

        return function ($root, array $args, $context, ResolveInfo $resolveInfo) use ($subscription, $subscriptionRegistry) {
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

            $subscriptionRegistry->registerSubscriber(
                $subscriber,
                $subscription->encodeTopic($subscriber, $this->getFieldName())
            );

            return null;
        };
    }

    /**
     * If a default namespace exists for the parent type, return it.
     *
     * @return string|null
     */
    public function getDefaultNamespaceForParent()
    {
        switch ($this->getParentName()) {
            case 'Query':
                return config('lighthouse.namespaces.queries');
            case 'Mutation':
                return config('lighthouse.namespaces.mutations');
            case 'Subscription':
                return config('lighthouse.namespaces.subscriptions');
            default:
                return null;
        }
    }

    /**
     * @return StringValueNode|null
     */
    public function getDescription()
    {
        return $this->field->description;
    }

    /**
     * Get current complexity.
     *
     * @return \Closure|null
     */
    public function getComplexity()
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
}
