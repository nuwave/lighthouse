<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use Nuwave\Lighthouse\Schema\Directives\Directive;
use Nuwave\Lighthouse\Schema\Values\NodeValue;

interface NodeMiddleware extends Directive
{
    /**
     * Handle node value.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function handleNode(NodeValue $value);
}
