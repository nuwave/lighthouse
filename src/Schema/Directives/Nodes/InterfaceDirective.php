<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Type\Definition\UnionType;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Traits\HandlesTypes;
use Nuwave\Lighthouse\Schema\Factories\TypeFactory;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class InterfaceDirective extends BaseDirective implements NodeResolver
{
    use HandlesTypes;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'interface';
    }

    /**
     * Resolve the node directive.
     *
     * @param NodeValue $value
     *
     * @return UnionType
     */
    public function resolveNode(NodeValue $value)
    {
        return TypeFactory::resolveInterfaceType(
            $value,
            function () use ($value) {
                return $this->getFields($value);
            },
            function ($value) {
                return $this->getResolver()($value);
            }
        );
    }
}
