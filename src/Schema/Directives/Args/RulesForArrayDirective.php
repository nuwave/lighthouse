<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\HasErrorBuffer;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddlewareForArray;

class RulesForArrayDirective extends BaseDirective implements ArgMiddlewareForArray, HasErrorBuffer, HasArgumentPath
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
