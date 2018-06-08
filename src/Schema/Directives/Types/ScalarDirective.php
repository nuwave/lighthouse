<?php

namespace Nuwave\Lighthouse\Schema\Directives\Types;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Resolvers\ScalarResolver;
use Nuwave\Lighthouse\Schema\Values\TypeValue;

class ScalarDirective implements TypeResolver
{
    /**
     * Name of the directive.
     *
     * @var string
     *
     * @return string
     */
    public static function name()
    {
        return 'scalar';
    }

    /**
     * Resolve the node directive.
     *
     * @param TypeValue $value
     *
     * @return Type
     */
    public function resolveType(TypeValue $value)
    {
        return ScalarResolver::resolveType($value);
    }
}
