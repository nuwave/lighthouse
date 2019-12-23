<?php

namespace Tests\Utils\Directives;

use Nuwave\Lighthouse\Schema\Directives\RulesDirective;

class CustomRulesDirective extends RulesDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'customRules';
    }
}
