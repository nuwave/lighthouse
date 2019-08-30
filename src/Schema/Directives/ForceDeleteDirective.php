<?php

namespace Nuwave\Lighthouse\Schema\Directives;

class ForceDeleteDirective extends DeleteRestoreDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'forceDelete';
    }
}
