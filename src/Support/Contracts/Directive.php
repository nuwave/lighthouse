<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface Directive
{
    /**
     * Formal directive specification in schema definition language (SDL).
     *
     * @return string
     */
    public static function definition(): string;
}
