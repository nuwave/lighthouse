<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class NodeDirective implements NodeMiddleware
{
    use HandlesDirectives;

    /**
     * Directive name.
     *
     * @return string
     */
    public function name()
    {
        return 'node';
    }

    /**
     * Handle type construction.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function handle(NodeValue $value)
    {
        graphql()->nodes()->node(
            $value->getNodeName(),
            $this->getResolver($value),
            $this->getResolveType($value)
        );

        $this->registerInterface($value);

        return $value;
    }

    /**
     * Get node resolver.
     *
     * @param NodeValue $value
     *
     * @return \Closure
     */
    protected function getResolver(NodeValue $value)
    {
        $resolver = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), $this->name()),
            'resolver'
        );

        $namespace = array_get(explode('@', $resolver), '0');
        $method = array_get(explode('@', $resolver), '1');

        return function ($id) use ($namespace, $method) {
            $instance = app($namespace);

            return call_user_func_array([$instance, $method], [$id]);
        };
    }

    /**
     * Get interface type resolver.
     *
     * @param NodeValue $value
     *
     * @return \Closure
     */
    protected function getResolveType(NodeValue $value)
    {
        $resolver = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), $this->name()),
            'typeResolver'
        );

        $namespace = array_get(explode('@', $resolver), '0');
        $method = array_get(explode('@', $resolver), '1');

        return function ($value) use ($namespace, $method) {
            $instance = app($namespace);

            return call_user_func_array([$instance, $method], [$value]);
        };
    }

    /**
     * Register Node interface.
     *
     * @param NodeValue $value
     */
    protected function registerInterface(NodeValue $value)
    {
        if (! $value->hasInterface('Node')) {
            $value->attachInterface('Node');
        }
    }
}
