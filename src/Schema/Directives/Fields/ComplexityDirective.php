<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class ComplexityDirective extends BaseFieldDirective implements FieldMiddleware
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
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value)
    {
        $baseClassName = $this->associatedArgValue('class') ?? str_before($this->associatedArgValue('resolver'), '@');

        if (empty($baseClassName)) {
            return $value->setComplexity(function ($childrenComplexity, $args) {
                $complexity = array_get($args, 'first', array_get($args, 'count', 1));

                return $childrenComplexity * $complexity;
            });
        }

        $resolverClass = $this->namespaceClassName($baseClassName);
        $resolverMethod = $this->associatedArgValue('method') ?? str_after($this->associatedArgValue('resolver'), '@');

        if (!method_exists($resolverClass, $resolverMethod)) {
            throw new DirectiveException("Method '{$resolverMethod}' does not exist on class '{$resolverClass}'");
        }

        return $value->setComplexity(function () use ($resolverClass, $resolverMethod) {
            return call_user_func_array([app($resolverClass), $resolverMethod], func_get_args());
        });
    }
}
