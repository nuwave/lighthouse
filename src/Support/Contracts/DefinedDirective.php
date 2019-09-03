<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface DefinedDirective
{
    /**
     * SDL definition of the directive.
     *
     * @return string
     */
    public static function definition();
}
