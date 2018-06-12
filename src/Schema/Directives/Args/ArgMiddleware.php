<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Directives\Directive;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

interface ArgMiddleware extends Directive
{
    /**
     * Apply transformations on the ArgumentValue.
     *
     * @param ArgumentValue $argument
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argument);
}
