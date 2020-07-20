<?php

namespace Tests\Utils\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class FooDirective extends BaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Maximum bar.
"""
directive @foo on FIELD_DEFINITION
SDL;
    }
}
