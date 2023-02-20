<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgSanitizerDirective extends Directive
{
    /**
     * Sanitize the value of an argument given to a field.
     *
     * @param  mixed  $argumentValue  The value given by the client
     *
     * @return mixed the sanitized value
     */
    public function sanitize($argumentValue);
}
