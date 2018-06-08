<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Resolvers\ScalarResolver;
use Nuwave\Lighthouse\Schema\Values\NodeValue;

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
     * @param NodeValue $value
     *
     * @return Type
     */
    public function resolveType(NodeValue $value)
    {
        return ScalarResolver::resolveType($value);
    }
}
