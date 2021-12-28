<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Subscriptions\Directives\SubscriptionDirective;
use Nuwave\Lighthouse\Subscriptions\Exceptions\UnauthorizedSubscriber;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver;
use Nuwave\Lighthouse\Support\Utils;

class SubscriptionResolverProvider implements ProvidesSubscriptionResolver
{
    /**
     * @var \Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry
     */
    protected $subscriptionRegistry;

    public function __construct(SubscriptionRegistry $subscriptionRegistry)
    {
        $this->subscriptionRegistry = $subscriptionRegistry;
    }

    /**
     * Provide a resolver for a subscription field in case no resolver directive is defined.
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     *
     * @return \Closure(mixed, array<string, mixed>, \Nuwave\Lighthouse\Support\Contracts\GraphQLContext, \GraphQL\Type\Definition\ResolveInfo): mixed
     */
    public function provideSubscriptionResolver(FieldValue $fieldValue): Closure
    {
        $fieldName = $fieldValue->getFieldName();

        $directive = ASTHelper::directiveDefinition($fieldValue->getField(), SubscriptionDirective::NAME);
        if (null !== $directive) {
            $className = ASTHelper::directiveArgValue($directive, 'class');
        } else {
            $className = Str::studly($fieldName);
        }

        $namespacesToTry = RootType::defaultNamespaces($fieldValue->getParentName());
        $className = Utils::namespaceClassname(
            $className,
            $namespacesToTry,
            function (string $class): bool {
                return is_subclass_of($class, GraphQLSubscription::class);
            }
        );

        if (! $className) {
            $subscriptionClass = GraphQLSubscription::class;
            $consideredNamespaces = implode(', ', $namespacesToTry);
            throw new DefinitionException(
                "Failed to find class {$className} extends {$subscriptionClass} in namespaces [{$consideredNamespaces}] for the subscription field {$fieldName}"
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

            return null;
        };
    }
}
