<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class SecurityDirective implements NodeMiddleware
{
    use HandlesDirectives;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'security';
    }

    /**
     * Handle node value.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function handleNode(NodeValue $value)
    {
        if ('Query' !== $value->getNodeName()) {
            $message = sprintf(
                'The `%s` directive can only be placed on the %s type [%s]',
                self::name(),
                'Query',
                $value->getNodeName()
            );

            throw new DirectiveException($message);
        }

        $this->queryDepth($value);
        $this->queryComplexity($value);
        $this->queryIntrospection($value);

        return $value;
    }

    /**
     * Set the max query complexity.
     *
     * @param NodeValue $value
     */
    protected function queryComplexity(NodeValue $value)
    {
        $complexity = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), self::name()),
            'complexity'
        );

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
        $depth = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), self::name()),
            'depth'
        );

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
        $introspection = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), self::name()),
            'introspection'
        );

        if (false === $introspection) {
            DocumentValidator::addRule(new DisableIntrospection());
        }
    }
}
