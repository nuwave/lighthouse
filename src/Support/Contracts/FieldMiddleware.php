<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

interface FieldMiddleware extends Directive
{
    /**
     * Wrap around the final field resolver.
     *
     * @param  \Closure(\Nuwave\Lighthouse\Schema\Values\FieldValue $field): \Nuwave\Lighthouse\Schema\Values\FieldValue  $next
     *
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $fieldValue, Closure $next);
}
