<?php

namespace Nuwave\Lighthouse\Schema\Directives;

class UnionDirective extends BaseDirective
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
}
