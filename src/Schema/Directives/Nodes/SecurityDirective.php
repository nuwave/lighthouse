<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Validator\Rules\QueryDepth;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use GraphQL\Validator\Rules\DisableIntrospection;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

/**
 * Class SecurityDirective
 * @package Nuwave\Lighthouse\Schema\Directives\Nodes
 * @deprecated will be defined through the config file as of v3
 */
class SecurityDirective extends BaseDirective implements NodeMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'security';
    }

    /**
     * Handle node value.
     *
     * @param NodeValue $value
     * @param \Closure $next
     *
     * @throws DirectiveException
     *
     * @return NodeValue
     */
    public function handleNode(NodeValue $value, \Closure $next)
    {
        if ('Query' !== $value->getNodeName()) {
            $message = sprintf(
                'The `security` directive can only be placed on the %s type [%s]',
                'Query',
                $value->getNodeName()
            );

            throw new DirectiveException($message);
        }

        $this->queryDepth();
        $this->queryComplexity();
        $this->queryIntrospection();

        return $next($value);
    }

    /**
     * Set the max query complexity.
     */
    protected function queryComplexity()
    {
        $complexity = $this->directiveArgValue('complexity');

        if ($complexity) {
            config(['lighthouse.security.max_query_complexity' => $complexity]);
        }
    }

    /**
     * Set max query depth.
     */
    protected function queryDepth()
    {
        $depth = $this->directiveArgValue('depth');

        if ($depth) {
            config(['lighthouse.security.max_query_depth' => $depth]);
        }
    }

    /**
     * Set introspection rule.
     */
    protected function queryIntrospection()
    {
        $enableIntrospection = $this->directiveArgValue('introspection');

        if (false === $enableIntrospection) {
            config(['lighthouse.security.disable_introspection' => !$enableIntrospection]);
        }
    }
}
