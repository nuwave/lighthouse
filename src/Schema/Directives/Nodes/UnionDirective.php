<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use Nuwave\Lighthouse\Schema\Factories\TypeFactory;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class UnionDirective extends BaseDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'union';
    }

    /**
     * Resolve the node directive.
     *
     * @param NodeValue $value
     *
     * @return Type
     */
    public function resolveNode(NodeValue $value)
    {
        $resolver = $this->getResolver();

        return TypeFactory::resolveUnionType($value, function ($value) use ($resolver) {
            return $resolver($value);
        });
    }
}
