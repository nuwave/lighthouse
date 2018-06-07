<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use Nuwave\Lighthouse\Schema\Directives\Directive;
use Nuwave\Lighthouse\Schema\Values\NodeValue;

interface NodeResolver extends Directive
{
    /**
     * Resolve the node directive.
     *
     * @param NodeValue $value
     *
     * @return mixed
     */
    public function resolveNode(NodeValue $value);
}
