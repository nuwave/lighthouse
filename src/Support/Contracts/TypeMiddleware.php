<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Closure;
use Nuwave\Lighthouse\Schema\Values\TypeValue;

interface TypeMiddleware extends Directive
{
    /**
     * Handle a type AST as it is converted to an executable type.
     *
     * @return \GraphQL\Type\Definition\Type
     */
    public function handleNode(TypeValue $value, Closure $next);
}
