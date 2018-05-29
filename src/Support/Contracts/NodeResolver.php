<?php

namespace Nuwave\Lighthouse\Support\Contracts;

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
