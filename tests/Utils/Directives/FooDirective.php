<?php

namespace Tests\Utils\Directives;

use Nuwave\Lighthouse\Support\Contracts\Directive;

class FooDirective implements Directive
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'foo';
    }
}
