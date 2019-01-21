<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

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
