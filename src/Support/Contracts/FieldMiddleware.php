<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

interface FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name();

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @param Closure $next
     * @return FieldValue
     */
    public function handleField(FieldValue $value, Closure $next);
}
