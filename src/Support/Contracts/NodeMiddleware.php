<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\NodeValue;

interface NodeMiddleware extends Directive
{
    /**
     * Handle node value.
     *
     * @param NodeValue $value
     * @param \Closure   $next
     *
     * @return NodeValue
     */
    public function handleNode(NodeValue $value, \Closure $next);
}
