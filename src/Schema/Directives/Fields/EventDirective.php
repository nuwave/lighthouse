<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class EventDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'event';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     * @param \Closure    $next
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next)
    {
        $eventBaseName = $this->directiveArgValue('fire') ?? $this->directiveArgValue('class');
        $eventClassName = $this->namespaceClassName($eventBaseName);
        $resolver = $value->getResolver();

        return $next($value->setResolver(function () use ($resolver, $eventClassName) {
            $args = func_get_args();
            $value = call_user_func_array($resolver, $args);
            event(new $eventClassName($value));

            return $value;
        }));
    }
}
