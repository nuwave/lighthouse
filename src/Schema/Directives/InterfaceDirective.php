<?php

namespace Nuwave\Lighthouse\Schema\Directives;

class InterfaceDirective extends BaseDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'interface';
    }
}
