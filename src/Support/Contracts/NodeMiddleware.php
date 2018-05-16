<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Closure;
use Nuwave\Lighthouse\Schema\Values\NodeValue;

interface NodeMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name();

    /**
     * Handle node value.
     *
     * @param NodeValue $value
     *
     * @param Closure $next
     * @return NodeValue
     */
    public function handleNode(NodeValue $value, Closure $next);
}
