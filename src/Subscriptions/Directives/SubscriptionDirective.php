<?php

namespace Nuwave\Lighthouse\Subscriptions\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;

/**
 * This directive exists as a placeholder and can be used
 * to point to a custom subscription class.
 *
 * @see \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription
 */
class SubscriptionDirective extends BaseDirective implements Directive
{
    public const NAME = 'subscription';

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Reference a class to handle the broadcasting of a subscription to clients.
The given class must extend `\Nuwave\Lighthouse\Schema\Types\GraphQLSubscription`.
"""
directive @subscription(
  """
  A reference to a subclass of `\Nuwave\Lighthouse\Schema\Types\GraphQLSubscription`.
  """
  class: String!
) on FIELD_DEFINITION
GRAPHQL;
    }
}
