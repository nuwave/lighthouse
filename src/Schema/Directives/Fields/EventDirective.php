<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class EventDirective extends BaseFieldDirective implements FieldMiddleware
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
     *
     * @return FieldValue
     * @throws \Nuwave\Lighthouse\Support\Exceptions\DirectiveException
     */
    public function handleField(FieldValue $value)
    {
        $eventBaseName = $this->associatedArgValue('fire') ?? $this->associatedArgValue('class');
        $eventClassName = $this->namespaceClassName($eventBaseName);
        $resolver = $value->getResolver();

        return $value->setResolver(function () use ($resolver, $eventClassName) {
            $args = func_get_args();
            $value = call_user_func_array($resolver, $args);
            event(new $eventClassName($value));

            return $value;
        });
    }
}
