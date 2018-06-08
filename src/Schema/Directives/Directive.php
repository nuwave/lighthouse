<?php

namespace Nuwave\Lighthouse\Schema\Directives;

interface Directive
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name();
}
