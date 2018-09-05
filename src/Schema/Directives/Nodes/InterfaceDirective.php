<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\InterfaceType;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Traits\HandlesTypes;
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
     * @return Type
     */
    public function resolveNode(NodeValue $value)
    {
        return new InterfaceType([
            'name' => $value->getNodeName(),
            'description' => $value->getNode()->description,
            'fields' => function () use ($value) {
                return $this->getFields($value);
            },
            'resolveType' => function ($value){
                return $this->getResolver()($value);
            },
        ]);
    }
}
