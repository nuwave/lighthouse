<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Closure;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Support\Utils;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Schema\Directives\SubscriptionDirective;
use Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver;
use Nuwave\Lighthouse\Subscriptions\Exceptions\UnauthorizedSubscriber;

class SubscriptionResolverProvider implements ProvidesSubscriptionResolver
{
    /**
     * @var \Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry
     */
    protected $subscriptionRegistry;

    /**
     * ResolverProvider constructor.
     *
     * @param  \Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry  $subscriptionRegistry
     * @return void
     */
    public function __construct(SubscriptionRegistry $subscriptionRegistry)
    {
        $this->subscriptionRegistry = $subscriptionRegistry;
    }

    /**
     * Provide a field resolver in case no resolver directive is defined for a field.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Closure
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function provideSubscriptionResolver(FieldValue $fieldValue): Closure
    {
        $fieldName = $fieldValue->getFieldName();

        if ($directive = ASTHelper::directiveDefinition($fieldValue->getField(), SubscriptionDirective::NAME)) {
            $className = ASTHelper::directiveArgValue($directive, 'class');
        } else {
            $className = Str::studly($fieldName);
        }

        $className = Utils::namespaceClassname(
            $className,
            $fieldValue->defaultNamespacesForParent(),
            function (string $class): bool {
                return is_subclass_of($class, GraphQLSubscription::class);
            }
        );

        if (! $className) {
            throw new DefinitionException(
                "No class found for the subscription field {$fieldName}"
            );
        }

        /** @var \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription $subscription */
        $subscription = app($className);

        // Subscriptions can only be placed on a single field on the root
        // query, so there is no need to consider the field path
        $this->subscriptionRegistry->register(
            $subscription,
            $fieldName
        );

        return function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($subscription, $fieldName) {
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

            $this->subscriptionRegistry->subscriber(
                $subscriber,
                $subscription->encodeTopic($subscriber, $fieldName)
            );
        };
    }
}
