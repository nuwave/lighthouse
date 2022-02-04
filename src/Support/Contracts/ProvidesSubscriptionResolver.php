<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

interface ProvidesSubscriptionResolver
{
    /**
     * Provide a resolver for a subscription field in case no resolver directive is defined.
     *
     * This function is expected to call @see \Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry::register().
     */
    public function provideSubscriptionResolver(FieldValue $fieldValue): Closure;
}
