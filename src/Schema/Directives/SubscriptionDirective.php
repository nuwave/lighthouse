<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\Directive;

/**
 * This directive exists as a placeholder and can be used
 * to point to a custom subscription class.
 *
 * @see \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription
 */
class SubscriptionDirective implements Directive
{
    const NAME = 'subscription';

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return self::NAME;
    }
}
