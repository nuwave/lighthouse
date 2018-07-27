<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\FieldValue;

interface FieldMiddleware extends Directive
{
    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     * @param \Closure    $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next);
}
