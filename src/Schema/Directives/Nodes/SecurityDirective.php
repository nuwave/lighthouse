<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use Closure;
use GraphQL\Validator\Rules\QueryDepth;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use GraphQL\Validator\Rules\DisableIntrospection;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

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
     * @param Closure   $next
     *
     * @return NodeValue
     */
    public function handleNode(NodeValue $value, Closure $next)
    {
        if ('Query' !== $value->getNodeName()) {
            $message = sprintf(
                'The `security` directive can only be placed on the %s type [%s]',
                'Query',
                $value->getNodeName()
            );

            throw new DirectiveException($message);
        }

        $this->queryDepth($value);
        $this->queryComplexity($value);
        $this->queryIntrospection($value);

        return $next($value);
    }

    /**
     * Set the max query complexity.
     *
     * @param NodeValue $value
     */
    protected function queryComplexity(NodeValue $value)
    {
        $complexity = $this->directiveArgValue('complexity');

        if ($complexity) {
            DocumentValidator::addRule(
                new QueryComplexity($complexity)
            );
        }
    }

    /**
     * Set max query depth.
     *
     * @param NodeValue $value
     */
    protected function queryDepth(NodeValue $value)
    {
        $depth = $this->directiveArgValue('depth');

        if ($depth) {
            DocumentValidator::addRule(
                new QueryDepth($depth)
            );
        }
    }

    /**
     * Set introspection rule.
     *
     * @param NodeValue $value
     */
    protected function queryIntrospection(NodeValue $value)
    {
        $introspection = $this->directiveArgValue('introspection');

        if (false === $introspection) {
            DocumentValidator::addRule(
                new DisableIntrospection()
            );
        }
    }
}
