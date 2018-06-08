<?php

namespace Nuwave\Lighthouse\Schema\Directives\Types;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Directives\Directive;
use Nuwave\Lighthouse\Schema\Values\TypeValue;

interface TypeResolver extends Directive
{
    /**
     * Resolve the Node type and set it on the TypeValue.
     *
     * @param TypeValue $value
     *
     * @return Type
     */
    public function resolveType(TypeValue $value);
}
