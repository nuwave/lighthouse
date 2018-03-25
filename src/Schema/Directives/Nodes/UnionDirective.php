<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Type\Definition\UnionType;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class UnionDirective implements NodeResolver
{
    use HandlesDirectives;

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
    public function resolve(NodeValue $value)
    {
        $resolver = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), $this->name()),
            'resolver'
        );

        $namespace = array_get(explode('@', $resolver), '0');
        $method = array_get(explode('@', $resolver), '1', strtolower($value->getNodeName()));

        return $value->setType(new UnionType([
            'name' => $value->getNodeName(),
            'description' => trim(str_replace("\n", '', $value->getNode()->description)),
            'types' => function () use ($value) {
                return collect($value->getNode()->types)->map(function ($type) {
                    return schema()->instance($type->name->value);
                })->filter()->toArray();
            },
            'resolveType' => function ($value) use ($namespace, $method) {
                $instance = app($namespace);

                return call_user_func_array([$instance, $method], [$value]);
            },
        ]));
    }
}
