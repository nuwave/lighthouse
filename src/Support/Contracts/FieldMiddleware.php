<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

interface FieldMiddleware extends Directive
{
    /**
     * Wrap around the final field resolver.
     *
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $fieldValue, Closure $next);
}
