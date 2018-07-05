<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Resolvers\ScalarResolver;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;

class ScalarDirective implements NodeResolver
{
    /**
     * Name of the directive.
     *
     * @var string
     * @return string
     */
    public function name(): string
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
    public function resolveNode(NodeValue $value): Type
    {
        return ScalarResolver::resolve($value)->getType();
    }
}
