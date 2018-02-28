<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

interface ArgMiddleware
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
     * @param ArgumentValue $argument
     *
     * @return array
     */
    public function handle(ArgumentValue $argument);
}
