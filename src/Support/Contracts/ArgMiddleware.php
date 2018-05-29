<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

interface ArgMiddleware extends Directive
{
    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $argument
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argument);
}
