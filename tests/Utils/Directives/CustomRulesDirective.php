<?php

namespace Tests\Utils\Directives;

use Nuwave\Lighthouse\Schema\Directives\RulesDirective;

class CustomRulesDirective extends RulesDirective
{
    public function name(): string
    {
        return 'customRules';
    }
}
