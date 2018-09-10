<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\MiddlewareRegistry;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class MiddlewareDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'middleware';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     * @param \Closure    $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next)
    {
        $checks = $this->getChecks($value);

        if ($checks) {
            $middlewareRegistry = resolve(MiddlewareRegistry::class);

            if ('Query' === $value->getNodeName()) {
                $middlewareRegistry->registerQuery(
                    $value->getFieldName(),
                    $checks
                );
            } elseif ('Mutation' === $value->getNodeName()) {
                $middlewareRegistry->registerMutation(
                    $value->getFieldName(),
                    $checks
                );
            }
        }

        return $next($value);
    }

    /**
     * Get middleware checks.
     *
     * @param FieldValue $value
     *
     * @return array|null
     */
    protected function getChecks(FieldValue $value)
    {
        if (! in_array($value->getNodeName(), ['Mutation', 'Query'])) {
            return null;
        }

        $checks = $this->directiveArgValue('checks');

        if (! $checks) {
            return null;
        }

        if (is_string($checks)) {
            $checks = [$checks];
        }

        return $checks;
    }
}
