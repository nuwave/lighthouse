<?php

namespace Nuwave\Lighthouse\Support\Contracts;

/**
 * @deprecated will be integrated into
 * @see \Nuwave\Lighthouse\Support\Contracts\Directive
 */
interface DefinedDirective
{
    /**
     * SDL definition of the directive.
     *
     * @return string
     */
    public static function definition();
}
