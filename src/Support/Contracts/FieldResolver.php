<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\FieldValue;

interface FieldResolver extends Directive
{
    /**
     * Set a field resolver on the FieldValue.
     *
     * This must call $fieldValue->setResolver() before returning
     * the FieldValue.
     *
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function resolveField(FieldValue $fieldValue);
}
