<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgSanitizerDirective extends Directive
{
    /**
     * Sanitize the value of an argument given to a field.
     */
    public function sanitize($argumentValue);
}
