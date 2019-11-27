<?php

namespace Tests\Integration\Console;

use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;

class UnionDirective implements Directive, DefinedDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'union';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Some other definition then the original.
"""
directive @union on UNION
SDL;
    }
}
