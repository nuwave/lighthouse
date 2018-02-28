<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\Node;

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
     * @param Node $node
     *
     * @return mixed
     */
    public function resolve(Node $node);
}
