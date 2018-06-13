<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;

class AuthDirective extends AbstractFieldDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'auth';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return \Closure
     */
    public function resolveField(FieldValue $value)
    {
        $guard = $this->associatedArgValue('name');

        return function () use ($guard) {
            return auth($guard)->user();
        };
    }
}
