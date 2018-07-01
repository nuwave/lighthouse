<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class EventDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
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
    public function handleField(FieldValue $value): FieldValue
    {
        $eventBaseName = $this->directiveArgValue('fire') ?? $this->directiveArgValue('class');
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
