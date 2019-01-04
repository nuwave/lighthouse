<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Support\Contracts\Directive;

/**
 * This directive exists as a placeholder and can be used
 * to point to a custom SubscriptionField class.
 */
class SubscriptionDirective implements Directive
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'subscription';
    }
}
