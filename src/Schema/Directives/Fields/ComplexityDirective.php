<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

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
     * @return FieldValue
     */
    public function handleField(FieldValue $value)
    {
        $directive = $this->fieldDirective($value->getField(), $this->name());

        if ($resolver = $this->getResolver($value, $directive, false)) {
            $method = $this->getResolverMethod($directive);

            return $value->setComplexity(function () use ($resolver, $method) {
                return call_user_func_array([app($resolver), $method], func_get_args());
            });
        }

        return $value->setComplexity(function ($childrenComplexity, $args) {
            $complexity = array_get($args, 'first', array_get($args, 'count', 1));

            return $childrenComplexity * $complexity;
        });
    }
}
