<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Type\Definition\UnionType;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;

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
     * @return mixed
     */
    public function resolveNode(NodeValue $value)
    {
        $resolver = $this->directiveArgValue('resolver');

        $namespace = array_get(explode('@', $resolver), '0');
        $method = array_get(explode('@', $resolver), '1', strtolower($value->getNodeName()));

        return $value->setType(new UnionType([
            'name' => $value->getNodeName(),
            'description' => trim(str_replace("\n", '', $value->getNode()->description)),
            'types' => function () use ($value) {
                return collect($value->getNode()->types)->map(function ($type) {
                    return graphql()->types()->instance($type->name->value);
                })->filter()->toArray();
            },
            'resolveType' => function ($value) use ($namespace, $method) {
                if ($namespace) {
                    $instance = app($namespace);
                    return call_user_func_array([$instance, $method], [$value]);
                }
                return graphql()->types()->instance(last(explode('\\', get_class($value))));
            },
        ]));
    }
}
