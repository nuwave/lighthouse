<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class InjectDirective implements FieldMiddleware
{
    use HandlesDirectives;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'inject';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @param Closure $next
     * @return FieldValue
     * @throws DirectiveException
     */
    public function handleField(FieldValue $value, Closure $next)
    {
        $resolver = $value->getResolver();
        $attr = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), $this->name()),
            'context'
        );

        $name = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), $this->name()),
            'name'
        );

        if (! $attr) {
            throw new DirectiveException(sprintf(
                'The `inject` directive on %s [%s] must have a `context` argument',
                $value->getNodeName(),
                $value->getFieldName()
            ));
        }

        if (! $name) {
            throw new DirectiveException(sprintf(
                'The `inject` directive on %s [%s] must have a `name` argument',
                $value->getNodeName(),
                $value->getFieldName()
            ));
        }

        $value->setResolver(function () use ($attr, $name, $resolver) {
            $args = func_get_args();
            $context = $args[2];
            $args[1] = array_merge($args[1], [$name => data_get($context, $attr)]);

            return call_user_func_array($resolver, $args);
        });

        return $next($value);
    }
}
