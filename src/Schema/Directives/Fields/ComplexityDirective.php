<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Traits\CanParseResolvers;

class ComplexityDirective implements FieldMiddleware
{
    use CanParseResolvers;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'complexity';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @param Closure $next
     * @return FieldValue
     * @throws \Nuwave\Lighthouse\Support\Exceptions\DirectiveException
     */
    public function handleField(FieldValue $value, Closure $next)
    {
        $directive = $this->fieldDirective($value->getField(), $this->name());

        if ($resolver = $this->getResolver($value, $directive, false)) {
            $method = $this->getResolverMethod($directive);

            return $value->setComplexity(function () use ($resolver, $method) {
                return call_user_func_array([app($resolver), $method], func_get_args());
            });
        }

        $value->setComplexity(function ($childrenComplexity, $args) {
            $complexity = array_get($args, 'first', array_get($args, 'count', 1));

            return $childrenComplexity * $complexity;
        });

        return $next($value);
    }
}
