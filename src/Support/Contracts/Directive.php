<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface Directive
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name();
}
