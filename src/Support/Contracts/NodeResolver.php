<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\NodeValue;

interface NodeResolver extends Directive
{
    /**
     * Resolve the NodeValue to a GraphQL Type.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\NodeValue  $value
     * @return \GraphQL\Type\Definition\Type
     */
    public function resolveNode(NodeValue $value);
}
