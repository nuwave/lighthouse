<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\Node;

abstract class NodeDirective
{
    /**
     * Resolve the node directive.
     *
     * @param Node $node
     *
     * @return mixed
     */
    abstract public function resolve(Node $node);
}
