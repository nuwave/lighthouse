<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use Closure;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeNodeMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class ModelDirective implements NodeNodeMiddleware
{
    use HandlesDirectives;

    /**
     * Directive name.
     *
     * @return string
     */
    public function name()
    {
        return 'model';
    }

    /**
     * Handle node value.
     *
     * @param NodeValue $value
     * @param Closure $next
     * @return NodeValue
     */
    public function handle(NodeValue $value, Closure $next): NodeValue
    {
        $namespace = $this->getNamespace($value);

        graphql()->nodes()->model(
            $value->getNodeName(), $namespace
        );

        $this->registerInterface($value);

        return $next($value);
    }

    /**
     * Get model namespace.
     *
     * @param NodeValue $value
     *
     * @return string
     */
    protected function getNamespace(NodeValue $value)
    {
        $namespace = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), $this->name()),
            'class'
        );

        if ($namespace) {
            return $namespace;
        }

        return config('lighthouse.namespaces.models').'\\'.$value->getNodeName();
    }

    /**
     * Register Node interface.
     *
     * @param NodeValue $value
     */
    protected function registerInterface(NodeValue $value)
    {
        if (! $value->hasInterface('Node')
            && ! is_null(config('lighthouse.global_id_field'))
        ) {
            $value->attachInterface('Node');
        }
    }
}
