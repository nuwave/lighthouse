<?php

namespace Nuwave\Lighthouse\Schema\Directives;

class DeleteDirective extends DeleteRestoreDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'delete';
    }
}
