<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface Directive
{
    /**
     * Name of the directive as used in the schema.
     *
     * @return string
     */
    public function name();
}
