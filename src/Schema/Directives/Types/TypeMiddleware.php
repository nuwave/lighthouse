<?php

namespace Nuwave\Lighthouse\Schema\Directives\Types;

use Nuwave\Lighthouse\Schema\Directives\Directive;
use Nuwave\Lighthouse\Schema\Values\TypeValue;

interface TypeMiddleware extends Directive
{
    /**
     * Handle node value.
     *
     * @param TypeValue $value
     *
     * @return TypeValue
     */
    public function handleNode(TypeValue $value);
}
