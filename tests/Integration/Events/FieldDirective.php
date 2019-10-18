<?php

namespace Tests\Integration\Events;

use Nuwave\Lighthouse\Support\Contracts\Directive;

class FieldDirective implements Directive
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'field';
    }
}
