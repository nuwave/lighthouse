<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class ComplexityDirective extends BaseDirective implements FieldMiddleware
{
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
     * @param Closure    $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, Closure $next)
    {
        $baseClassName = $this->directiveArgValue('class') ?? str_before($this->directiveArgValue('resolver'), '@');

        if (empty($baseClassName)) {
            return $next($value->setComplexity(function ($childrenComplexity, $args) {
                $complexity = array_get($args, 'first', array_get($args, 'count', 1));

                return $childrenComplexity * $complexity;
            }));
        }

        $resolverClass = $this->namespaceClassName($baseClassName);
        $resolverMethod = $this->directiveArgValue('method') ?? str_after($this->directiveArgValue('resolver'), '@');

        if (! method_exists($resolverClass, $resolverMethod)) {
            throw new DirectiveException("Method '{$resolverMethod}' does not exist on class '{$resolverClass}'");
        }

        return $next($value->setComplexity(function () use ($resolverClass, $resolverMethod) {
            return call_user_func_array([app($resolverClass), $resolverMethod], func_get_args());
        }));
    }
}
