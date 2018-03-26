<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Type\Definition\InterfaceType;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesTypes;

class InterfaceDirective implements NodeResolver
{
    use HandlesDirectives, HandlesTypes;

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
     * @return mixed
     */
    public function resolve(NodeValue $value)
    {
        $resolver = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), $this->name()),
            'resolver'
        );

        $instance = app(array_get(explode('@', $resolver), '0'));
        $method = array_get(explode('@', $resolver), '1');

        return $value->setType(new InterfaceType([
            'name' => $value->getNodeName(),
            'description' => trim(str_replace("\n", '', $value->getNode()->description)),
            'fields' => function () use ($value) {
                return $this->getFields($value);
            },
            'resolveType' => function ($value) use ($instance, $method) {
                return call_user_func_array([$instance, $method], [$value]);
            },
        ]));
    }
}
