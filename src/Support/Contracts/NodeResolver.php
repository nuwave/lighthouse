<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\NodeValue;

interface NodeResolver extends Directive
{
    /**
     * Resolve a type AST to a GraphQL Type.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\NodeValue  $value
     * @return \GraphQL\Type\Definition\Type
     */
    public function resolveNode(NodeValue $value);
}
