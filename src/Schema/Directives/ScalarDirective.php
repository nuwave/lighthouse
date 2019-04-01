<?php

namespace Nuwave\Lighthouse\Schema\Directives;

class ScalarDirective extends BaseDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'scalar';
    }
}
