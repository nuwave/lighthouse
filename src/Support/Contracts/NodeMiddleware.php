<?php

namespace Nuwave\Lighthouse\Support\Contracts;

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
     * @return NodeValue
     */
    public function handle(NodeValue $value);
}
