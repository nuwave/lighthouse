<?php

namespace Tests\Console;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;

class UnionDirective extends BaseDirective implements Directive
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Some other definition then the original.
"""
directive @union on UNION
GRAPHQL;
    }
}
