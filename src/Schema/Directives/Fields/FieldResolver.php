<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Directives\Directive;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

interface FieldResolver extends Directive
{
    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return \Closure
     */
    public function resolveField(FieldValue $value);
}
