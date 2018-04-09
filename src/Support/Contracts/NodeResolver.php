<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\NodeValue;

interface NodeResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name();

    /**
     * Resolve the node directive.
     *
     * @param NodeValue $value
     *
     * @return mixed
     */
    public function resolveNode(NodeValue $value);
}
