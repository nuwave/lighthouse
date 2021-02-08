<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface Directive
{
    /**
     * Formal directive specification in schema definition language (SDL).
     *
     * @see https://spec.graphql.org/draft/#sec-Type-System.Directives
     *
     * This must contain a single directive definition, but can also contain
     * auxiliary types, such as enum definitions for directive arguments.
     */
    public static function definition(): string;
}
