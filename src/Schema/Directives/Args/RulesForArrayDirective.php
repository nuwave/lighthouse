<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgValidationDirective;

class RulesForArrayDirective extends BaseDirective implements ArgValidationDirective, ArgDirectiveForArray, HasArgumentPath
{
    use HandleRulesDirective;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'rulesForArray';
    }
}
