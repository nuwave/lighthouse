<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\FieldValue;

interface FieldMiddleware extends Directive
{
    /**
     * Wrap around the final field resolver.
     */
    public function handleField(FieldValue $fieldValue): void;
}
