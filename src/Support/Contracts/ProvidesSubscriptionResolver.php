<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

interface ProvidesSubscriptionResolver
{
    /**
     * Provide a resolver for a subscription field in case no resolver directive is defined.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Closure
     */
    public function provideSubscriptionResolver(FieldValue $fieldValue): Closure;
}
