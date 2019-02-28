<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Closure;
use Nuwave\Lighthouse\Schema\Values\NodeValue;

interface NodeMiddleware extends Directive
{
    /**
     * Handle node value.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\NodeValue  $value
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\NodeValue
     */
    public function handleNode(NodeValue $value, Closure $next);
}
