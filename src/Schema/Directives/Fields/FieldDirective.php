<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class FieldDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'field';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @throws DirectiveException
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $value)
    {
        $baseClassName = $this->directiveArgValue('class')
            ?? str_before($this->directiveArgValue('resolver'), '@');


        if (empty($baseClassName)) {
            $directiveName = $this->name();
            throw new DirectiveException("Directive '{$directiveName}' must have a `class` argument.");
        }

        $resolverClass = $this->namespaceClassName($baseClassName);
        $resolverMethod = $this->directiveArgValue('method')
            ?? str_after($this->directiveArgValue('resolver'), '@');

        if (! method_exists($resolverClass, $resolverMethod)) {
            throw new DirectiveException("Method '{$resolverMethod}' does not exist on class '{$resolverClass}'");
        }

        $additionalData = $this->directiveArgValue('args');

        return $value->setResolver(
            function ($root, array $args, $context = null, $info = null) use ($resolverClass, $resolverMethod, $additionalData) {
                return call_user_func_array(
                    [app($resolverClass), $resolverMethod],
                    [$root, array_merge($args, ['directive' => $additionalData]), $context, $info]
                );
            }
        );
    }
}
