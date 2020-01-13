<?php

namespace Tests\Integration\Console;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;

class UnionDirective extends BaseDirective implements Directive, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Some other definition then the original.
"""
directive @union on UNION
SDL;
    }
}
