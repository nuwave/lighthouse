<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class UnionDirective extends BaseDirective implements NodeResolver
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
        $resolver = $this->getResolver(
            function() use ($value){
                return graphql()->types()->get(
                    str_after('\\', get_class($value))
                );
            }
        );

        return new UnionType([
            'name' => $value->getNodeName(),
            'description' => $value->getNode()->description,
            'types' => function () use ($value) {
                return collect($value->getNode()->types)->map(function ($type) {
                    return graphql()->types()->get($type->name->value);
                })->filter()->toArray();
            },
            'resolveType' => function ($value) use ($resolver) {
                return $resolver($value);
            },
        ]);
    }
}
