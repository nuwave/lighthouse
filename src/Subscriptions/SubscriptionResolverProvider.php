<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Subscriptions\Directives\SubscriptionDirective;
use Nuwave\Lighthouse\Subscriptions\Exceptions\UnauthorizedSubscriber;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver;
use Nuwave\Lighthouse\Support\Utils;

class SubscriptionResolverProvider implements ProvidesSubscriptionResolver
{
    public function __construct(
        protected SubscriptionRegistry $subscriptionRegistry,
    ) {}

    /**
     * Provide a resolver for a subscription field in case no resolver directive is defined.
     *
     * @return \Closure(mixed, array<string, mixed>, \Nuwave\Lighthouse\Support\Contracts\GraphQLContext, \Nuwave\Lighthouse\Execution\ResolveInfo): mixed
     */
    public function provideSubscriptionResolver(FieldValue $fieldValue): \Closure
    {
        $fieldName = $fieldValue->getFieldName();

        $directive = ASTHelper::directiveDefinition($fieldValue->getField(), SubscriptionDirective::NAME);
        $className = $directive === null
            ? Str::studly($fieldName)
            : ASTHelper::directiveArgValue($directive, 'class');

        $namespacesToTry = $fieldValue->parentNamespaces();
        $namespacedClassName = Utils::namespaceClassname(
            $className,
            $namespacesToTry,
            static fn (string $class): bool => is_subclass_of($class, GraphQLSubscription::class),
        );

        if ($namespacedClassName === null) {
            $subscriptionClass = GraphQLSubscription::class;
            $consideredNamespaces = implode(', ', $namespacesToTry);
            throw new DefinitionException("Failed to find class {$className} extends {$subscriptionClass} in namespaces [{$consideredNamespaces}] for the subscription field {$fieldName}.");
        }

        assert(is_subclass_of($namespacedClassName, GraphQLSubscription::class));

        $subscription = Container::getInstance()->make($namespacedClassName);
        // Subscriptions can only be placed on a single field on the root
        // query, so there is no need to consider the field path
        $this->subscriptionRegistry->register($subscription, $fieldName);

        return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($subscription, $fieldName) {
            if ($root instanceof Subscriber) {
                return $subscription->resolve($root->root, $args, $context, $resolveInfo);
            }

            $subscriber = new Subscriber($args, $context, $resolveInfo);

            if (! $subscription->can($subscriber)) {
                throw new UnauthorizedSubscriber('Unauthorized subscription request');
            }

            $this->subscriptionRegistry->subscriber(
                $subscriber,
                $subscription->encodeTopic($subscriber, $fieldName),
            );

            return null;
        };
    }
}
