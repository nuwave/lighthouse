<?php

namespace Tests\Unit\Console;

use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

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
