<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

interface ProvidesResolver
{
    /**
     * Provide a field resolver in case no resolver directive is defined for a field.
     */
    public function provideResolver(FieldValue $fieldValue): Closure;
}
