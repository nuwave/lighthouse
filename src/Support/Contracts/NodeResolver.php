<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Values\NodeValue;

interface NodeResolver extends Directive
{
    /**
     * Resolve the NodeValue to a GraphQL Type.
     *
     * @param  \GraphQL\Language\AST\NodeValue  $value
     *
     * @return \GraphQL\Type\Definition\Type
     */
    public function resolveNode(NodeValue $value);
}
