<?php

namespace Nuwave\Lighthouse\Schema\Directives;

class RestoreDirective extends DeleteRestoreDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'restore';
    }
}
