<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use Closure;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Types\InterfaceType;
use Nuwave\Lighthouse\Support\Contracts\NodeNodeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesTypes;

class InterfaceDirective implements NodeNodeMiddleware
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
     * Handle node value.
     *
     * @param NodeValue $value
     * @param Closure $next
     * @return NodeValue
     */
    public function handle(NodeValue $value, Closure $next): NodeValue
    {
        $resolver = $value->getNode()->directive($this->name())->arg("resolver");

        $instance = app(array_get(explode('@', $resolver), '0'));
        $method = array_get(explode('@', $resolver), '1');

        $value->setType(graphql()->typeRepository()->create(
            InterfaceType::class,
            $value->getNodeName(),
            function () use ($value) {
                return $value->getNode()->fields();
            },
            function ($value) use ($instance, $method) {
                return call_user_func_array([$instance, $method], [$value]);
            },
            $value->getNode()->description()
        ));

        return $next($value);
    }
}
