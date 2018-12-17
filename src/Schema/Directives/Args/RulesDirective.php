<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath;
use Nuwave\Lighthouse\Support\Contracts\ArgValidationDirective;

class RulesDirective extends BaseDirective implements ArgValidationDirective, HasArgumentPath
{
    use HandleRulesDirective;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'rules';
    }
}
