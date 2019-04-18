<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Closure;
use Nuwave\Lighthouse\Schema\Values\NodeValue;

interface NodeMiddleware extends Directive
{
    /**
     * Handle a type AST as it is converted to an executable type.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\NodeValue  $value
     * @param  \Closure  $next
     * @return \GraphQL\Type\Definition\Type
     */
    public function handleNode(NodeValue $value, Closure $next);
}
