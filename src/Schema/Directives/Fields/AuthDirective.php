<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Contracts\Auth\Authenticatable;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class AuthDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'auth';
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     *
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $guard = $this->directiveArgValue('guard');

        return $fieldValue->setResolver(
            function () use ($guard): ?Authenticatable {
                return auth($guard)->user();
            }
        );
    }
}
