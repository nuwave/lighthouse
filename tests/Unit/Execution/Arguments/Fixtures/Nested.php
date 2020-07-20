<?php

namespace Tests\Unit\Execution\Arguments\Fixtures;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\Directive;

class Nested extends BaseDirective implements ArgResolver, Directive
{
    public function __invoke($root, $args): void
    {
    }
}
