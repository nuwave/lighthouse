<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\Node;

abstract class NodeDirective
{
    /**
     * Name of the directive.
     *
     * @var string
     */
    abstract public function name();

    /**
     * Resolve the node directive.
     *
     * @param Node $node
     *
     * @return mixed
     */
    abstract public function resolve(Node $node);
}
