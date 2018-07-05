<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Values\NodeValue;

interface NodeResolver extends Directive
{
    /**
     * Resolve the NodeValue to a GraphQL Type.
     *
     * @param NodeValue $value
     *
     * @return Type
     */
    public function resolveNode(NodeValue $value);
}
