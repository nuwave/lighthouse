<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use Closure;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeNodeMiddleware;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class GroupDirective implements NodeNodeMiddleware
{
    use HandlesDirectives;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'group';
    }

    /**
     * Handle node value.
     *
     * @param NodeValue $value
     *
     * @param Closure $next
     * @return NodeValue
     * @throws DirectiveException
     */
    public function handle(NodeValue $value, Closure $next): NodeValue
    {
        $this->setNamespace($value);
        $this->setMiddleware($value);

        return $next($value);
    }

    /**
     * Set namespace on node.
     *
     * @param NodeValue $value [description]
     */
    protected function setNamespace(NodeValue $value)
    {
        $namespace = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), $this->name()),
            'namespace'
        );

        if ($namespace) {
            $value->setNamespace($namespace);
        }
    }

    /**
     * Set middleware for field.
     *
     * @param NodeValue $value
     */
    protected function setMiddleware(NodeValue $value)
    {
        $node = $value->getNodeName();

        if (! in_array($node, ['Query', 'Mutation'])) {
            $message = 'Middleware can only be placed on a Query or Mutation ['.$node.']';

            throw new DirectiveException($message);
        }

        $middleware = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), $this->name()),
            'middleware'
        );

        $container = graphql()->middleware();
        $middleware = is_string($middleware) ? [$middleware] : $middleware;

        if (empty($middleware)) {
            return;
        }

        foreach ($value->getNodeFields() as $field) {
            'Query' == $node
                ? $container->registerQuery($field->name->value, $middleware)
                : $container->registerMutation($field->name->value, $middleware);
        }
    }
}
