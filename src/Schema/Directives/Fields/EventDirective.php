<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class EventDirective extends AbstractFieldDirective implements FieldMiddleware
{
    use HandlesDirectives;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'event';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @throws DirectiveException
     *
     * @return Closure
     */
    public function handleField(FieldValue $value)
    {
        $eventBaseName = $this->associatedArgValue('fire')
            // Default to reading this from class
            ?? $this->associatedArgValue('class');

        $eventClassName = $this->namespaceClassName($eventBaseName);

        $resolver = $value->getResolver();

        return function () use ($resolver, $eventClassName) {
            $args = func_get_args();
            $value = call_user_func_array($resolver, $args);
            event(new $eventClassName($value));

            return $value;
        };
    }
}
