<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Closure;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class EventDirective implements FieldMiddleware
{
    use HandlesDirectives;

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
     * @param Closure $next
     * @return FieldValue
     */
    public function handleField(FieldValue $value, Closure $next)
    {
        $event = $this->getEvent($value->getField());
        $resolver = $value->getResolver();

        $value->setResolver(function () use ($resolver, $event) {
            $args = func_get_args();
            $value = call_user_func_array($resolver, $args);
            event(new $event($value));

            return $value;
        });

        return $next($value);
    }

    /**
     * Get the event name.
     *
     * @param FieldDefinitionNode $field
     *
     * @return mixed
     */
    protected function getEvent(FieldDefinitionNode $field)
    {
        return $this->directiveArgValue(
            $this->fieldDirective($field, 'event'),
            'fire',
            $this->directiveArgValue(
                $this->fieldDirective($field, 'event'),
                'class'
            )
        );
    }
}
