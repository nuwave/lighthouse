<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\DirectiveNode;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

trait CanParseResolvers
{
    use HandlesDirectives;

    /**
     * Get resolver namespace.
     *
     * @param FieldValue    $value
     * @param DirectiveNode $directive\
     * @param bool          $throw
     *
     * @return string
     */
    protected function getResolver(FieldValue $value, DirectiveNode $directive, $throw = true)
    {
        if ($resolver = $this->directiveArgValue($directive, 'resolver')) {
            $className = array_get(explode('@', $resolver), '0');

            return $value->getNamespace($className);
        }

        return $value->getNamespace(
            $this->getResolverClassName($directive, $throw)
        );
    }

    /**
     * Get class name for resolver.
     *
     * @param DirectiveNode $directive
     * @param bool          $throw
     *
     * @return string
     */
    protected function getResolverClassName(DirectiveNode $directive, $throw = true)
    {
        $class = $this->directiveArgValue($directive, 'class');

        if (! $class && $throw) {
            throw new DirectiveException(sprintf(
                'Directive [%s] must have a `class` argument.',
                $directive->name->value
            ));
        }

        return $class;
    }

    /**
     * Get method for resolver.
     *
     * @param DirectiveNode $directive
     *
     * @return string
     */
    protected function getResolverMethod(DirectiveNode $directive)
    {
        if ($resolver = $this->directiveArgValue($directive, 'resolver')) {
            if ($method = array_get(explode('@', $resolver), '1')) {
                return $method;
            }
        }

        $method = $this->directiveArgValue($directive, 'method');

        if (! $method) {
            throw new DirectiveException(sprintf(
                'Directive [%s] must have a `method` argument.',
                $directive->name->value
            ));
        }

        return $method;
    }
}
