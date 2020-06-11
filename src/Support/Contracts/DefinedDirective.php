<?php

namespace Nuwave\Lighthouse\Support\Contracts;

/**
 * @deprecated The method definition() will be moved to
 * @see \Nuwave\Lighthouse\Support\Contracts\Directive
 *
 * Keep using this interface for now.
 * When upgrading to the upcoming v5, just remove it.
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
