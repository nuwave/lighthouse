<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface Directive
{
    /**
     * Formal directive specification in schema definition language (SDL).
     */
    public static function definition(): string;
}
