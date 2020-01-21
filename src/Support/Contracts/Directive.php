<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface Directive
{
    /**
     * Name of the directive as used in the schema.
     *
     * @deprecated will be replaced by definition(): string
     * @see \Nuwave\Lighthouse\Support\Contracts\DefinedDirective
     *
     * @return string
     */
    public function name();
}
