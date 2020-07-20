<?php

namespace Tests\Unit\Execution\Arguments\Fixtures;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class Nested extends BaseDirective implements ArgResolver
{
    public function __invoke($root, $args): void
    {
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ 'directive @nested on FIELD_DEFINITION';
    }
}
