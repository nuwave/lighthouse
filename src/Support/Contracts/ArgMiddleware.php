<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

interface ArgMiddleware
{
    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $argument
     *
     * @return array
     */
    public function handle(ArgumentValue $argument);
}
