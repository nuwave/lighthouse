<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Directives\Directive;
use Nuwave\Lighthouse\Schema\Values\NodeValue;

interface TypeResolver extends Directive
{
    /**
     * Resolve the Node type and set it on the NodeValue.
     *
     * @param NodeValue $value
     *
     * @return Type
     */
    public function resolveType(NodeValue $value);
}
