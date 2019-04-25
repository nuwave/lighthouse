<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

interface ProvidesSubscriptionResolver
{
    /**
     * Provide a field resolver for subscriptions.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Closure
     */
    public function provideSubscriptionResolver(FieldValue $fieldValue): Closure;
}
