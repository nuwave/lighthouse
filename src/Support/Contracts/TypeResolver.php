<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\TypeValue;

interface TypeResolver extends Directive
{
    /**
     * Resolve a type AST to a GraphQL Type.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\TypeValue  $value
     * @return \GraphQL\Type\Definition\Type
     */
    public function resolveNode(TypeValue $value);
}
